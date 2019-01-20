<?php

namespace OPNsense\UserMapping\Api;


use OPNsense\Auth\AuthenticationFactory;
use OPNsense\Base\ApiControllerBase;
use OPNsense\UserMapping\BackendAPI;
use OPNsense\UserMapping\UserMapping;

class SessionController extends ApiControllerBase
{
    private $whitelisted_actions = array('login', 'logout', 'who_am_i');
    private $is_authenticated = false;
    private $user_mapping = null;
    private $user = null;
    private $auth_props = null;
    private $client_ip = null;

    /**
     * log in on the network and tell the backend about the sesson state.
     * @return array information about the authentication state
     */
    public function loginAction() {
        if (empty($this->user)) {
            return array('error' => 'not authenticated');
        }
        $backend = new BackendAPI();
        return $backend->log_in($this->client_ip, $this->user, $this->get_user_groups());
    }

    /**
     * log out (kill the sesson in the deamon)
     * @return array containing the status text
     */
    public function logoutAction() {
        if (!empty($this->user)) {
            $backend = new BackendAPI();
            return $backend->log_out($this->client_ip);
        }
        return ['error' => 'not authenticated'];
    }

    public function listAction() {
        $backend = new BackendAPI();
        return $backend->list();
    }

    public function who_isAction($ip) {
        $backend = new BackendAPI();
        return $backend->who_is($ip);
    }

    public function who_am_iAction() {
        return $this->who_isAction($this->client_ip);
    }

    /**
     * @return array returns a list of group names (type: string)
     */
    private function get_user_groups() {
        $ret = array();
        if (isset($this->auth_props['groups'])) {
            foreach ($this->auth_props['groups'] as $group) {
                $ret[] = (string)$group->name;
            }
        }
        return $ret;
    }

    public function beforeExecuteRoute($dispatcher) {
        $this->response->setHeader('Cache-Control', 'no-cache');
        $this->client_ip = $this->request->getClientAddress();
        $this->user_mapping = new UserMapping();
        // we do not offer standard authentication before we are called
        if (in_array($dispatcher->getActionName(), $this->whitelisted_actions)) {
            $this->parseJsonBodyData(); // just in case
            if (!empty($this->request->getHeader('Authorization'))) {
                // check for basic auth
                $this->perform_header_authentication();
            }
        } else {
            parent::beforeExecuteRoute($dispatcher);
        }
    }

    private function authenticate_against_server($user_name, $password) {
        $authFactory = new AuthenticationFactory();
        // currently only one can be configured but we may allow more in the future
        $auth_server = (string)$this->user_mapping->configuration->authentication_backend;
        if (empty($auth_server)) {
            $auth_server = 'Local Database';
        }
        foreach (explode(',', $auth_server) as $auth_server_name) {
            $auth_server = $authFactory->get(trim($auth_server_name));
            if ($auth_server != null) {
                // try this auth method
                $this->is_authenticated = $auth_server->authenticate($user_name, $password);
                if ($this->is_authenticated) {
                    $this->user = $user_name;
                    $this->auth_props = $auth_server->getLastAuthProperties();
                    return;
                }
            }
        }
    }

    private function perform_header_authentication(): void
    {
        $auth_header_data = explode(' ', trim($this->request->getHeader('Authorization')), 2);
        if (count($auth_header_data) == 2) {
            switch ($auth_header_data[0]) {
                case 'Basic':
                    $tmp_basic = explode(':', base64_decode($auth_header_data[1]), '2');
                    if (count($tmp_basic) == 2) {
                        $this->authenticate_against_server($tmp_basic[0], $tmp_basic[1]);
                    }
                    break;
                default:
                    break;
            }
        }
    }

}