<?php

/**
 * Port Forward API Rules Controller
 * Provides CRUD operations for NAT port forward rules
 */

namespace OPNsense\PortForwardApi\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Config;

class RulesController extends ApiControllerBase
{
    /**
     * List all port forward rules
     * GET /api/portforwardapi/rules/list
     * @return array
     */
    public function listAction()
    {
        $config = Config::getInstance()->object();
        $rules = [];

        if (isset($config->nat->rule)) {
            foreach ($config->nat->rule as $rule) {
                $rules[] = [
                    'interface' => (string)$rule->interface,
                    'protocol' => (string)$rule->protocol,
                    'src_address' => isset($rule->source->address) ? (string)$rule->source->address : 'any',
                    'src_port' => isset($rule->source->port) ? (string)$rule->source->port : '',
                    'dst_address' => isset($rule->destination->address) ? (string)$rule->destination->address :
                                    (isset($rule->destination->network) ? (string)$rule->destination->network : 'any'),
                    'dst_port' => isset($rule->destination->port) ? (string)$rule->destination->port : '',
                    'target' => (string)$rule->target,
                    'local_port' => (string)$rule->{'local-port'},
                    'descr' => (string)$rule->descr,
                    'disabled' => isset($rule->disabled) ? '1' : '0',
                    'associated_rule_id' => isset($rule->{'associated-rule-id'}) ? (string)$rule->{'associated-rule-id'} : '',
                ];
            }
        }

        return ['rules' => $rules];
    }

    /**
     * Add new port forward rule
     * POST /api/portforwardapi/rules/add
     * @return array
     */
    public function addAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        $post = $this->request->getPost('rule');
        if (empty($post)) {
            // Try JSON body
            $post = json_decode(file_get_contents('php://input'), true);
            $post = $post['rule'] ?? $post;
        }

        if (empty($post['target']) || empty($post['local_port']) || empty($post['dst_port'])) {
            return ['status' => 'error', 'message' => 'Required fields: target, local_port, dst_port'];
        }

        // Validate target IP address
        if (!filter_var($post['target'], FILTER_VALIDATE_IP)) {
            return ['status' => 'error', 'message' => 'Invalid target IP address'];
        }

        // Validate ports (single port or range like "80:443")
        if (!$this->isValidPort($post['dst_port'])) {
            return ['status' => 'error', 'message' => 'Invalid destination port (1-65535 or range like 80:443)'];
        }
        if (!$this->isValidPort($post['local_port'])) {
            return ['status' => 'error', 'message' => 'Invalid local port (1-65535 or range like 80:443)'];
        }

        // Validate protocol if provided
        $validProtocols = ['tcp', 'udp', 'tcp/udp'];
        $protocol = $post['protocol'] ?? 'tcp';
        if (!in_array(strtolower($protocol), $validProtocols)) {
            return ['status' => 'error', 'message' => 'Invalid protocol (tcp, udp, or tcp/udp)'];
        }

        $config = Config::getInstance();
        $configObj = $config->object();

        // Ensure nat section exists
        if (!isset($configObj->nat)) {
            $configObj->addChild('nat');
        }

        // Generate associated rule ID with entropy for collision resistance
        $associatedRuleId = uniqid('nat_', true);

        // Create NAT rule
        $rule = $configObj->nat->addChild('rule');
        $rule->addChild('interface', $post['interface'] ?? 'wan');
        $rule->addChild('protocol', $post['protocol'] ?? 'tcp');
        $rule->addChild('target', $post['target']);
        $rule->addChild('local-port', $post['local_port']);

        if (!empty($post['descr'])) {
            $rule->addChild('descr', $post['descr']);
        }

        // Check if rule should be disabled
        if (!empty($post['disabled']) && ($post['disabled'] === '1' || $post['disabled'] === true)) {
            $rule->addChild('disabled', '1');
        }

        // Source
        $source = $rule->addChild('source');
        if (empty($post['src_address']) || $post['src_address'] == 'any') {
            $source->addChild('any', '1');
        } else {
            $source->addChild('address', $post['src_address']);
        }

        // Destination
        $dest = $rule->addChild('destination');
        if (empty($post['dst_address']) || $post['dst_address'] == 'wanip') {
            $dest->addChild('network', 'wanip');
        } else {
            $dest->addChild('address', $post['dst_address']);
        }
        $dest->addChild('port', $post['dst_port']);

        // Associated rule ID for filter rule linkage
        $rule->addChild('associated-rule-id', $associatedRuleId);

        // Create associated filter rule
        $this->createFilterRule($configObj, $post, $associatedRuleId);

        $config->save();

        return ['status' => 'ok', 'message' => 'Rule created', 'rule_id' => $associatedRuleId];
    }

    /**
     * Delete rule by associated_rule_id
     * POST /api/portforwardapi/rules/del/{ruleId}
     * @param string $ruleId - The associated-rule-id (e.g., "nat_6962b0ba0618d")
     * @return array
     */
    public function delAction($ruleId = null)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        if (empty($ruleId)) {
            return ['status' => 'error', 'message' => 'Rule ID required'];
        }

        $config = Config::getInstance();
        $configObj = $config->object();

        if (!isset($configObj->nat->rule)) {
            return ['status' => 'error', 'message' => 'No NAT rules found'];
        }

        // Find and delete the NAT rule by associated-rule-id
        $found = false;
        foreach ($configObj->nat->rule as $rule) {
            if (isset($rule->{'associated-rule-id'}) && (string)$rule->{'associated-rule-id'} === $ruleId) {
                $dom = dom_import_simplexml($rule);
                $dom->parentNode->removeChild($dom);
                $found = true;
                break;
            }
        }

        if (!$found) {
            return ['status' => 'error', 'message' => 'Rule not found with ID: ' . $ruleId];
        }

        // Delete associated filter rule
        $this->deleteFilterRule($configObj, $ruleId);

        $config->save();

        return ['status' => 'ok', 'message' => 'Rule deleted', 'rule_id' => $ruleId];
    }

    /**
     * Toggle rule enabled/disabled state
     * POST /api/portforwardapi/rules/toggle/{ruleId}
     * @param string $ruleId - The associated-rule-id
     * @return array
     */
    public function toggleAction($ruleId = null)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'error', 'message' => 'POST required'];
        }

        if (empty($ruleId)) {
            return ['status' => 'error', 'message' => 'Rule ID required'];
        }

        $config = Config::getInstance();
        $configObj = $config->object();

        if (!isset($configObj->nat->rule)) {
            return ['status' => 'error', 'message' => 'No NAT rules found'];
        }

        // Find the NAT rule
        $natRule = null;
        foreach ($configObj->nat->rule as $rule) {
            if (isset($rule->{'associated-rule-id'}) && (string)$rule->{'associated-rule-id'} === $ruleId) {
                $natRule = $rule;
                break;
            }
        }

        if ($natRule === null) {
            return ['status' => 'error', 'message' => 'Rule not found with ID: ' . $ruleId];
        }

        // Toggle the disabled state
        $wasDisabled = isset($natRule->disabled);
        if ($wasDisabled) {
            // Enable: remove disabled element
            unset($natRule->disabled);
            $newState = 'enabled';
        } else {
            // Disable: add disabled element
            $natRule->addChild('disabled', '1');
            $newState = 'disabled';
        }

        // Also toggle the associated filter rule
        $this->toggleFilterRule($configObj, $ruleId, !$wasDisabled);

        $config->save();

        return ['status' => 'ok', 'message' => "Rule $newState", 'rule_id' => $ruleId, 'disabled' => $wasDisabled ? '0' : '1'];
    }

    /**
     * Get rule count
     * GET /api/portforwardapi/rules/status
     * @return array
     */
    public function statusAction()
    {
        $config = Config::getInstance()->object();

        $count = 0;
        $enabled = 0;
        $disabled = 0;

        if (isset($config->nat->rule)) {
            foreach ($config->nat->rule as $rule) {
                $count++;
                if (isset($rule->disabled)) {
                    $disabled++;
                } else {
                    $enabled++;
                }
            }
        }

        return ['status' => 'ok', 'rule_count' => $count, 'enabled' => $enabled, 'disabled' => $disabled];
    }

    /**
     * Validate port number or range
     * @param string $port - Single port (80) or range (80:443)
     * @return bool
     */
    private function isValidPort($port)
    {
        // Single port
        if (preg_match('/^\d+$/', $port)) {
            $p = (int)$port;
            return $p >= 1 && $p <= 65535;
        }
        // Port range (e.g., 80:443)
        if (preg_match('/^(\d+):(\d+)$/', $port, $m)) {
            $start = (int)$m[1];
            $end = (int)$m[2];
            return $start >= 1 && $start <= 65535 && $end >= 1 && $end <= 65535 && $start <= $end;
        }
        return false;
    }

    /**
     * Create associated filter rule
     */
    private function createFilterRule($configObj, $post, $associatedRuleId)
    {
        if (!isset($configObj->filter)) {
            $configObj->addChild('filter');
        }

        $filterRule = $configObj->filter->addChild('rule');
        $filterRule->addChild('type', 'pass');
        $filterRule->addChild('interface', $post['interface'] ?? 'wan');
        $filterRule->addChild('protocol', $post['protocol'] ?? 'tcp');
        $filterRule->addChild('ipprotocol', 'inet');

        // If NAT rule is disabled, disable filter rule too
        if (!empty($post['disabled']) && ($post['disabled'] === '1' || $post['disabled'] === true)) {
            $filterRule->addChild('disabled', '1');
        }

        $source = $filterRule->addChild('source');
        $source->addChild('any', '1');

        $dest = $filterRule->addChild('destination');
        $dest->addChild('address', $post['target']);
        $dest->addChild('port', $post['local_port']);

        $filterRule->addChild('descr', 'NAT ' . ($post['descr'] ?? ''));
        $filterRule->addChild('associated-rule-id', $associatedRuleId);
    }

    /**
     * Delete associated filter rule
     */
    private function deleteFilterRule($configObj, $associatedRuleId)
    {
        if (!isset($configObj->filter->rule)) {
            return;
        }

        foreach ($configObj->filter->rule as $rule) {
            if (isset($rule->{'associated-rule-id'}) && (string)$rule->{'associated-rule-id'} === $associatedRuleId) {
                $dom = dom_import_simplexml($rule);
                $dom->parentNode->removeChild($dom);
                return;
            }
        }
    }

    /**
     * Toggle associated filter rule enabled/disabled state
     */
    private function toggleFilterRule($configObj, $associatedRuleId, $disable)
    {
        if (!isset($configObj->filter->rule)) {
            return;
        }

        foreach ($configObj->filter->rule as $rule) {
            if (isset($rule->{'associated-rule-id'}) && (string)$rule->{'associated-rule-id'} === $associatedRuleId) {
                if ($disable) {
                    if (!isset($rule->disabled)) {
                        $rule->addChild('disabled', '1');
                    }
                } else {
                    unset($rule->disabled);
                }
                return;
            }
        }
    }
}
