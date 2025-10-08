<?php
namespace OPNsense\UserProvision\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;

class UserController extends ApiControllerBase
{
    use AuditTrait;
    use ValidationTrait;
    public function listAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        if (empty($p) && stripos((string)$this->request->getHeader('Content-Type'), 'application/json') !== false) {
            $json = json_decode((string)$this->request->getRawBody(), true);
            if (is_array($json)) { $p = $json; }
        }
        $current = (int)($p['current'] ?? 1);
        $rowCount = (int)($p['rowCount'] ?? 25);
        $searchPhrase = strtolower(trim((string)($p['searchPhrase'] ?? '')));
        $cfg = Config::getInstance()->object();
        $rows = [];
        if (isset($cfg->system) && isset($cfg->system->user)) {
            foreach ($cfg->system->user as $usr) {
                $rows[] = [
                    'name' => (string)$usr->name,
                    'descr' => (string)$usr->descr
                ];
            }
        }
        if ($searchPhrase !== '') {
            $rows = array_values(array_filter($rows, function($r) use ($searchPhrase){
                return (strpos(strtolower($r['name']), $searchPhrase) !== false) || (strpos(strtolower($r['descr']), $searchPhrase) !== false);
            }));
        }
        $total = count($rows);
        if ($rowCount == -1) { $rowCount = $total; }
        elseif ($rowCount <= 0) { $rowCount = 25; }
        $current = max(1, $current);
        $offset = ($current - 1) * $rowCount;
        $pageRows = array_slice($rows, $offset, $rowCount);
        return ['current'=>$current,'rowCount'=>$rowCount,'rows'=>$pageRows,'total'=>$total];
    }

    public function deleteAction(): array
    {
        $username = trim((string)($this->request->getPost('username') ?? $this->request->get('username') ?? ''));
        if ($username === '' || !$this->isValidUsername($username)) { return ['status'=>'error','message'=>'invalid username']; }
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->system) || !isset($cfg->system->user)) { return ['status'=>'ok','deleted'=>false]; }
        $idx = 0; $deleted = false;
        foreach ($cfg->system->user as $usr) {
            if ((string)$usr->name === $username) {
                unset($cfg->system->user[$idx]);
                $deleted = true; break;
            }
            $idx++;
        }
        if ($deleted) { Config::getInstance()->save(); $this->audit('user.delete', ['username'=>$username]); }
        return ['status'=>'ok','deleted'=>$deleted];
    }
    public function upsertAction(): array
    {
        $u = $this->request->getPost() ?: $this->request->get();
        $username = trim((string)($u['username'] ?? ''));
        if ($username === '') { return ['status'=>'error','message'=>'missing username']; }
        $full_name = trim((string)($u['full_name'] ?? ''));
        $password = (string)($u['password'] ?? '');
        // groups parameters
        $mode = strtolower(trim((string)($u['mode'] ?? 'merge'))); // merge | replace
        $groupsParam = $u['groups'] ?? [];
        $groupsAddParam = $u['groups_add'] ?? [];
        $groupsRemoveParam = $u['groups_remove'] ?? [];
        $groups = is_array($groupsParam) ? $groupsParam : ( ($groupsParam==='') ? [] : [(string)$groupsParam] );
        $groups_add = is_array($groupsAddParam) ? $groupsAddParam : ( ($groupsAddParam==='') ? [] : [(string)$groupsAddParam] );
        $groups_remove = is_array($groupsRemoveParam) ? $groupsRemoveParam : ( ($groupsRemoveParam==='') ? [] : [(string)$groupsRemoveParam] );

        $cfg = Config::getInstance()->object();
        if (!isset($cfg->system)) { $cfg->addChild('system'); }
        if (!isset($cfg->system->user)) { $cfg->system->addChild('user'); }

        $existing = null; $idx = 0; $foundIdx = null;
        foreach ($cfg->system->user as $usr) {
            if ((string)$usr->name === $username) { $existing = $usr; $foundIdx = $idx; break; }
            $idx++;
        }
        if ($existing === null) {
            $usr = $cfg->system->addChild('user');
            $usr->addChild('name', $username);
            if ($full_name !== '') { $usr->addChild('descr', $full_name); }
            if ($password !== '') {
                if (!$this->isValidPassword($password)) { return ['status'=>'error','message'=>'weak password']; }
                $usr->addChild('password', password_hash($password, PASSWORD_DEFAULT));
            }
        } else {
            $usr = $existing;
            if ($full_name !== '') { $usr->descr = $full_name; }
            if ($password !== '') {
                if (!$this->isValidPassword($password)) { return ['status'=>'error','message'=>'weak password']; }
                $usr->password = password_hash($password, PASSWORD_DEFAULT);
            }
        }

        // ensure uid exists (required for group membership linking)
        $uid = (string)($usr->uid ?? '');
        if ($uid === '') {
            $maxUid = 0;
            if (isset($cfg->system->user)) {
                foreach ($cfg->system->user as $u2) {
                    $u2uid = (int)((string)($u2->uid ?? '0'));
                    if ($u2uid > $maxUid) { $maxUid = $u2uid; }
                }
            }
            $newUid = max(2000, $maxUid + 1);
            if (isset($usr->uid)) { unset($usr->uid); }
            $usr->addChild('uid', (string)$newUid);
            $uid = (string)$usr->uid;
        }
        // ensure groups for all potentially referenced names
        $allReferencedGroups = array_unique(array_filter(array_map('strval', array_merge($groups, $groups_add))));
        if (!isset($cfg->system->group)) { $cfg->system->addChild('group'); }
        foreach ($allReferencedGroups as $gname) {
            $has = false;
            foreach ($cfg->system->group as $grp) {
                if ((string)$grp->name === (string)$gname) { $has = true; break; }
            }
            if (!$has) {
                if (!$this->isValidGroupName((string)$gname)) { return ['status'=>'error','message'=>'invalid group']; }
                $grp = $cfg->system->addChild('group');
                $grp->addChild('name', (string)$gname);
                $grp->addChild('description', (string)$gname);
            }
        }
        // determine final user group set based on mode
        $existingUserGroups = [];
        if (isset($usr->groupname)) {
            foreach ($usr->groupname as $gn) { $existingUserGroups[] = (string)$gn; }
        }
        $existingUserGroups = array_values(array_unique($existingUserGroups));

        $finalGroups = $existingUserGroups;
        if ($mode === 'replace') {
            $finalGroups = array_values(array_unique(array_map('strval', $groups)));
        } else { // merge (default)
            // if 'groups' provided, treat as additions in merge mode
            $finalGroups = array_values(array_unique(array_merge($finalGroups, array_map('strval', $groups))));
            // also merge explicit groups_add
            $finalGroups = array_values(array_unique(array_merge($finalGroups, array_map('strval', $groups_add))));
            // remove explicit groups_remove
            if (!empty($groups_remove)) {
                $toRemove = array_map('strval', $groups_remove);
                $finalGroups = array_values(array_diff($finalGroups, $toRemove));
            }
        }

        // rewrite user groupname entries to reflect finalGroups
        unset($usr->groupname);
        foreach ($finalGroups as $gname) { $usr->addChild('groupname', (string)$gname); }

        // sync group memberships bi-directionally using uid in each group
        if (isset($cfg->system->group)) {
            foreach ($cfg->system->group as $grp) {
                $gname = (string)$grp->name;
                $isDesired = in_array($gname, $finalGroups, true);

                // collect current members
                $members = [];
                if (isset($grp->member)) {
                    foreach ($grp->member as $mem) { $members[] = (string)$mem; }
                }
                $has = in_array((string)$uid, $members, true);

                if ($isDesired && !$has) {
                    $grp->addChild('member', (string)$uid);
                } elseif (!$isDesired && $has) {
                    // remove only in replace mode or when explicitly requested
                    $shouldRemove = ($mode === 'replace') || in_array($gname, array_map('strval', $groups_remove), true);
                    if ($shouldRemove) {
                        $idx = 0;
                        foreach ($grp->member as $mem) {
                            if ((string)$mem === (string)$uid) { unset($grp->member[$idx]); }
                            $idx++;
                        }
                    }
                }
            }
        }

        Config::getInstance()->save();
        $this->audit('user.upsert', ['username'=>$username,'created'=>($existing===null),'groups'=>$groups]);
        return ['status'=>'ok','username'=>$username,'created'=>($existing===null)];
    }

    public function disableAction(): array
    {
        $username = trim((string)($this->request->getPost('username') ?? $this->request->get('username') ?? ''));
        if ($username === '' || !$this->isValidUsername($username)) { return ['status'=>'error','message'=>'invalid username']; }
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->system) || !isset($cfg->system->user)) { return ['status'=>'ok','updated'=>false]; }
        foreach ($cfg->system->user as $usr) {
            if ((string)$usr->name === $username) { $usr->disabled = '1'; Config::getInstance()->save(); $this->audit('user.disable', ['username'=>$username]); return ['status'=>'ok','updated'=>true]; }
        }
        return ['status'=>'ok','updated'=>false];
    }

    public function enableAction(): array
    {
        $username = trim((string)($this->request->getPost('username') ?? $this->request->get('username') ?? ''));
        if ($username === '' || !$this->isValidUsername($username)) { return ['status'=>'error','message'=>'invalid username']; }
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->system) || !isset($cfg->system->user)) { return ['status'=>'ok','updated'=>false]; }
        foreach ($cfg->system->user as $usr) {
            if ((string)$usr->name === $username) { unset($usr->disabled); Config::getInstance()->save(); $this->audit('user.enable', ['username'=>$username]); return ['status'=>'ok','updated'=>true]; }
        }
        return ['status'=>'ok','updated'=>false];
    }

    public function setpasswordAction(): array
    {
        $username = trim((string)($this->request->getPost('username') ?? $this->request->get('username') ?? ''));
        $password = (string)($this->request->getPost('password') ?? $this->request->get('password') ?? '');
        if ($username === '' || !$this->isValidUsername($username)) { return ['status'=>'error','message'=>'invalid username']; }
        if ($password === '' || !$this->isValidPassword($password)) { return ['status'=>'error','message'=>'weak password']; }
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->system) || !isset($cfg->system->user)) { return ['status'=>'ok','updated'=>false]; }
        foreach ($cfg->system->user as $usr) {
            if ((string)$usr->name === $username) { $usr->password = password_hash($password, PASSWORD_DEFAULT); Config::getInstance()->save(); $this->audit('user.setpassword', ['username'=>$username]); return ['status'=>'ok','updated'=>true]; }
        }
        return ['status'=>'ok','updated'=>false];
    }
}


