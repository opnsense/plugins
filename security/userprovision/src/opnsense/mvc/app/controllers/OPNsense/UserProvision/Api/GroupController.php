<?php
namespace OPNsense\UserProvision\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;

class GroupController extends ApiControllerBase
{
    use AuditTrait;
    use ValidationTrait;
    public function listAction(): array
    {
        $p = $this->request->getPost() ?: $this->request->get();
        $current = (int)($p['current'] ?? 1);
        $rowCount = (int)($p['rowCount'] ?? 25);
        $searchPhrase = strtolower(trim((string)($p['searchPhrase'] ?? '')));
        $cfg = \OPNsense\Core\Config::getInstance()->object();
        $rows = [];
        // Safe SimpleXML iteration
        if (isset($cfg->system) && isset($cfg->system->group) && $cfg->system->group instanceof \SimpleXMLElement) {
            foreach ($cfg->system->group as $grp) {
                $name = (string)($grp->name ?? '');
                $descr = (string)($grp->description ?? $grp->descr ?? '');
                $rows[] = [ 'name' => $name, 'descr' => $descr ];
            }
        }
        // Fallback via toArray() if no rows
        if (count($rows) === 0) {
            $arr = \OPNsense\Core\Config::getInstance()->toArray();
            $groups = $arr['system']['group'] ?? [];
            if (!empty($groups)) {
                if (!is_array($groups) || isset($groups['name'])) { $groups = [$groups]; }
                foreach ($groups as $g) {
                    $name = (string)($g['name'] ?? '');
                    $descr = (string)($g['description'] ?? $g['descr'] ?? '');
                    $rows[] = [ 'name' => $name, 'descr' => $descr ];
                }
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
        return ['current'=>$current,'rowCount'=>count($pageRows),'rows'=>$pageRows,'total'=>$total];
    }

    public function deleteAction(): array
    {
        $name = trim((string)($this->request->getPost('name') ?? $this->request->get('name') ?? ''));
        if ($name === '' || !$this->isValidGroupName($name)) { return ['status'=>'error','message'=>'invalid name']; }
        $cfg = \OPNsense\Core\Config::getInstance()->object();
        if (!isset($cfg->system) || !isset($cfg->system->group)) { return ['status'=>'ok','deleted'=>false]; }
        $idx = 0; $deleted = false;
        foreach ($cfg->system->group as $grp) {
            if ((string)$grp->name === $name) { unset($cfg->system->group[$idx]); $deleted = true; break; }
            $idx++;
        }
        if ($deleted) { \OPNsense\Core\Config::getInstance()->save(); $this->audit('group.delete', ['name'=>$name]); }
        return ['status'=>'ok','deleted'=>$deleted];
    }
    public function ensureAction(): array
    {
        $name = trim((string)($this->request->getPost('name') ?? $this->request->get('name') ?? ''));
        if ($name === '' || !$this->isValidGroupName($name)) { return ['status'=>'error','message'=>'invalid name']; }
        $cfg = Config::getInstance()->object();
        if (!isset($cfg->system)) { $cfg->addChild('system'); }
        if (!isset($cfg->system->group)) { $cfg->system->addChild('group'); }
        // search group
        foreach ($cfg->system->group as $grp) {
            if ((string)$grp->name === $name) { return ['status'=>'ok','created'=>false]; }
        }
        // create
        $grp = $cfg->system->addChild('group');
        $grp->addChild('name', $name);
        $grp->addChild('description', $name);
        Config::getInstance()->save();
        $this->audit('group.ensure', ['name'=>$name, 'created'=>true]);
        return ['status'=>'ok','created'=>true];
    }
}


