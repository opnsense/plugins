<?php

/**
 *    Copyright (C) 2018 Michael Muenz <m.muenz@gmail.com>
 *
 *    All rights reserved.
 *
 *    Redistribution and use in source and binary forms, with or without
 *    modification, are permitted provided that the following conditions are met:
 *
 *    1. Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *    2. Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *
 *    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 *    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 *    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 *    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 *    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 *    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 *    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 *    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 *    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 *    POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace OPNsense\Dnscryptproxy\Api;

use OPNsense\Base\ApiMutableModelControllerBase;
use OPNsense\Base\UserException;
use OPNsense\Core\Backend;
use OPNsense\Core\Config;
use OPNsense\Dnscryptproxy\ControllerBase;
use OPNsense\Dnscryptproxy\Settings;

/**
 * An ApiMutableModelControllerBase class used to perform settings related
 * actions for dnscrypt-proxy.
 *
 * This API is accessible at the following URL endpoint:
 *
 * `/api/dnscryptproxy/settings`
 *
 * This class creates the following API endpoints:
 * ```
 *   grid
 *   import
 *   export
 *   restoreSources
 * ```
 *
 * Functions with a name ending in "Action" become API endpoints by extending
 * `ApiMutableModelControllerBase`.

 * @package OPNsense\Dnscryptproxy
 */
class SettingsController extends ApiMutableModelControllerBase
{
    /**
     * This variable defines what to call the <model> that is defined for this
     * Class by Phalcon. That is to say the model XML that has the same name as
     * this controller's name, "Settings".
     * In this case, it is the model XML file:
     *
     * `model/OPNsense/Dnscryptproxy/Settings.xml`
     *
     * The model name is then used as the name of the array returned by setBase()
     * and getBase(). In the form XMLs, the prefix used on the field IDs must
     * match this name as API actions use the same name in their transactions.
     * For example, the key_name in an API JSON response, will be this model
     * name. This name is also used as the API endpoint for this Controller.
     *
     * `/api/dnscryptproxy/settings`
     *
     * This locks activies of this Class to this specific model, so it won't
     * save to other models, even within the same plugin.
     *
     * @var string $internalModelName
     */
    protected static $internalModelName = 'settings';

    /**
     * Base model class to reference.
     *
     * This variable defines which class to call for getMode(). It is used in a
     * ReflectionClass call to establish the model object. This class is defined
     * in the models directory alongside the model XML, and has the same name
     * as this Controller. This class extends BaseModel which reads the model
     * XML that has the same name as the class.
     *
     * In this case, these are the model XML file, and class definition file:
     *
     * `model/OPNsense/Dnscryptproxy/Settings.xml`
     *
     * `model/OPNsense/Dnscryptproxy/Settings.php`
     *
     * These together will establish several API endpoints on this Controller's
     * endpoint including:
     *
     * `/api/dnscryptproxy/settings/get`
     *
     * `/api/dnscryptproxy/settings/set`
     *
     * These are both defined in the ApiMutableModelControllerBase Class:
     *
     * `function getAction()`
     *
     * `function setAction()`
     *
     * @var string $internalModelClass
     */
    protected static $internalModelClass = 'OPNsense\Dnscryptproxy\Settings';

    /**
     * Array of pre-defined valid targets to use with the functions within
     * this class.
     *
     * @var array $validGridTargets
     */
    public static $validGridTargets = array(
        'allowed_names_internal.entries',
        'allowed_ips_internal.entries',
        'blocked_names_internal.entries',
        'blocked_ips_internal.entries',
        'schedules.schedule',
        'static.entries',
        'sources.source',
        'cloaking_internal.cloak',
        'forwards.forward',
        'doh_client_x509_auth.creds',
        'dns64.prefixes',
        'dns64.resolvers',
        'anonymized_dns.routes',
        'captive_portals.entries',
        'relays',
        'resolver_list',
    );

    /**
     * An API endpoint to call when no parameters are
     * provided for the API. Can be used to test the API is working.

     * API endpoint:
     *
     *   `/api/dnscryptproxy/settings`
     *
     * Usage:
     *
     *   `/api/dnscryptproxy/settings`
     *
     * Returns an array which gets converted to json in the POST response.
     *
     * @return array    includes status, saying everything is A-OK
     */
    public function indexAction()
    {
        return array('status' => 'ok');
    }

    /**
     * This is a super API for Bootgrid which will do all the things.
     *
     * Instead of having many copies of the same functions over and over, this
     * function replaces all of them, and it requires only setting a couple of
     * variables and adjusting the conditional statements to add or remove
     * grids.
     *
     * API endpoint:
     *
     *   `/api/dnscryptproxy/settings/grid`
     *
     * Parameters for this function are passed in via POST/GET request in the URL like so:
     * ```
     * |-------API Endpoint (Here)-----|api|----$target---|--------------$uuid----------------|
     * api/dnscryptproxy/settings/grid/get/servers.server/9d606689-19e0-48a7-84b2-9173525255d8
     * ```
     * This handles all of the bootgrid API calls, and keeps everything in
     * a single function. Everything is controled via the `$target` and
     * pre-defined variables which include the config path, and the
     * key name for the edit dialog.
     *
     * A note on the edit dialog, the `$key_name` must match the prefix of
     * the IDs of the fields defined in the form data for that dialog.
     *
     * Example:
     * ```
     *  <field>
     *     <id>server.enabled</id>
     *     <label>Enabled</label>
     *     <type>checkbox</type>
     *     <help>This will enable or disable the server stamp.</help>
     *  </field>
     * ```
     *
     * For the case above, the `$key_name` must be: "server"
     *
     * This correlates to the config path:
     *
     * `//OPNsense/dnscrypt-proxy/servers/server`
     *
     * `servers` is the ArrayField that these bootgrid functions are designed
     *           for.
     *
     * `server`  is the final node in the config path, and are
     *           entries in the ArrayField.
     *
     * The `$key_name`, the final node in the path, and the field ids in the form
     * XML must match. The field <id> is important because when `mapDataToFormUI()`
     * runs to populate the fields with data, the scope is just the dialog
     * box (which includes the fields). It will try to match ids with the
     * data it receives, and it splits up the ids at the period, using the
     * first element as its `key_name` for matching. This is also how the main
     * form works, and why all of those ids are prefixed with the model name.
     *
     * So get/set API calls return a JSON with a key named 'server', and the
     * data gets sent to fields which have a dotted prefix of the same name.
     * This links these elements together, though they are not directly
     * linked, only merely aligned together.
     *
     * Upon saving (using `setBase()`) it sends the POST data specified
     * in the function call wholesale, that array has to overlay perfectly
     * on the model.
     *
     * @param string       $action The desired action to take for the API call.
     * @param string       $target The desired pre-defined target for the API.
     * @param string       $uuid   The UUID of the target object.
     * @return array Array to be consumed by bootgrid.
     */
    public function gridAction($action, $target, $uuid = null)
    {
        if (
            $action === 'search' or
            $action === 'get' or
            $action === 'set' or
            $action === 'add' or
            $action === 'del' or
            $action === 'toggle'
        ) { // Check that we only operate on valid actions.
            if (in_array($target, self::$validGridTargets)) {        // Only operate on valid targets.
                $myController = new ControllerBase();
                $this_form = $myController->getForm('settings');   // Pull in the form data.

                $target_array = $this->searchFields($target, $this_form['tabs']);  // Search the form data for the $target.

                $tmp = explode('.', $target_array['path']);  // Split path on dots, have to use a temp var here.
                $key_name = end($tmp);                       // Get the last node from the path, and this will be our $key_name.

                // Create a Settings class object to use for configd_name.
                $settings = new Settings();

                switch (true) {
                    case ($action === 'search' && isset($target_array['fields'])):
                        if ($target === 'relays') { // Take care of custom searches first.
                            return $this->bootgridConfigd($settings->configd_name . ' get-relays', $target_array['fields']);
                        } elseif ($target === 'resolver_list') {
                            return $this->bootgridConfigd($settings->configd_name . ' get-resolvers', $target_array['fields']);
                        } elseif (isset($target_array['path'])) { // All other searches, check $target_array['path'] is set.
                            return $this->searchBase($target_array['path'], $target_array['fields']);
                        }
                        // no break
                    case ($action === 'get' && isset($key_name) && isset($target_array['path'])):
                        return $this->getBase($key_name, $target_array['path'], $uuid);
                    case ($action === 'add' && isset($key_name) && isset($target_array['path'])):
                        return $this->addBase($key_name, $target_array['path']);
                    case ($action === 'del' && isset($target_array['path']) && isset($uuid)):
                        return $this->delBase($target_array['path'], $uuid);
                    case ($action === 'set' && isset($key_name) && isset($target_array['path']) && isset($uuid)):
                        return $this->setBase($key_name, $target_array['path'], $uuid);
                    case ($action === 'toggle' && isset($target_array['path']) && isset($uuid)):
                        return $this->toggleBase($target_array['path'], $uuid);
                    default:
                        // If we get here it's probably a bug in this function.
                        $result['message'] = 'Some parameters were missing for action "' . $action . '" on target "' . $target . '"';
                }
            } else {
                $result['message'] = 'Unsupported target ' . $target;
            }
        } else {
            $result['message'] = 'Action "' . $action . '" not found.';
        }
        // Since we've gotten here, no valid options were presented,
        // we need to return a valid array for the bootgrid to consume though.
        $result['rows'] = array();
        $result['rowCount'] = 0;
        $result['total'] = 0;
        $result['current'] = 1;
        $result['status'] = 'failed';

        return $result;
    }

    /**
     * Form parser for searching for target fields within a form.
     *
     * This is used by gridAction() to dynamically pull paths, and fields from
     * the form data. This eliminates the need to re-define these values in
     * in this PHP class.
     *
     * This funciton also utilizes recursion to reduce code duplication.
     *
     * @param   string  $target The target to search for.
     * @param   array   $tabs   The form's tabs to be parsed.
     * @return  array           Array including 'path' and 'fields'.
     */
    private function searchFields($target, $tabs)
    {
        $target_array = array();

        foreach ($tabs as $tab) {
            // Perform recursion based on which type of tabs we have.
            // Return if we get something
            // Continue to the next tab we're done recursing.
            if (isset($tab['subtabs'])) {
                $target_array = $this->searchFields($target, $tab['subtabs']);
                if (! empty($target_array)) {
                    return $target_array;
                }

                continue;
            } elseif (isset($tab['tabs'])) {
                $target_array = $this->searchFields($target, $tab['tabs']);
                if (! empty($target_array)) {
                    return $target_array;
                }

                continue;
            }
            // This is where the tab is actually parsed.
            // Iterate through all of the elements, and look for fields.
            foreach ($tab[2] as $tab_element => $tab_element_value) {
                if ($tab_element == 'field') {
                    // This is for a corner case of only a single field on a tab
                    // We need to detect when SimpleXMLElement puts a field as the
                    // field array itself, instead of inside an array.
                    // We wrap it in an array to fix this.
                    if (! (isset($tab_element_value[0]))) {
                        $fields = [$tab_element_value];
                    } elseif ((is_array($tab_element_value[0]) || ($tab_element_value[0]) instanceof Traversable)) {
                        $fields = $tab_element_value;
                    }
                    foreach ($fields as $field) {
                        if (isset($field['type'])) {
                            if ($field['type'] == 'bootgrid') {
                                if (isset($field['target'])) {
                                    // Check if this is the field we're looking for.
                                    if ($field['target'] == $target) {
                                        $target_array['path'] = $target; //Found match, set path.
                                        if (isset($field['columns'])) {
                                            foreach ($field['columns'] as $field_element => $field_element_value) {
                                                if ($field_element == 'column') {
                                                    // This is another check for non-nested array corner case same as with field above.
                                                    // This will happen if there is only one column defined.
                                                    if (! ((is_array($field_element_value[0]) || ($field_element_value[0]) instanceof Traversable))) {
                                                        $column_var = [$field_element_value];
                                                    } elseif ((is_array($field_element_value[0]) || ($field_element_value[0]) instanceof Traversable)) {
                                                        $column_var = $field_element_value;
                                                    }
                                                    // Add each column's id as a field.
                                                    foreach ($column_var as $column) {
                                                        $target_array['fields'][] = $column['@attributes']['id'];
                                                    }
                                                }
                                            }
                                            // Add the enabled column if toggle api is present.
                                            foreach ($field['api'] as $field_element => $field_element_value) {
                                                if ($field_element == 'toggle') {
                                                    array_unshift($target_array['fields'], 'enabled');
                                                }
                                            }
                                            // We've found our target, and should have
                                            // target_array populated.
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        // Finally return our results.
        return $target_array;
    }

    /**
     * Export entries out of an ArrayField type node in the config..
     *
     * Uses a pre-defined set of targets (paths) to prevent arbitrary
     * export of data.
     *
     * API endpoint:
     *   /api/dnscryptproxy/settings/export
     *
     * Expects to receive a value "target" defined in the data in the GET
     * request.
     *
     * Example usage (in Javascript):
     * ajaxGet("/api/dnscryptproxy/settings/export",
     *          {"target": "allowed_names_internal"},
     *          function(data, status){...
     *
     * The second parameter is the data (array) where "target" is defined.
     */
    public function exportAction()
    {
        // Check that this function is being called by a GET request.
        if ($this->request->isGet()) {
            // Retrive the value of the target key in the GET request.
            $target = $this->request->get('target');
            if (! is_null($target)) {  // If we have a target, check it against the list.
                if (in_array($target, self::$validGridTargets)) {  // Gotta be on the VIP list.
                    $path = $this->$target;  // Set the path to the class var of the same name as the value of $target.
                } else {
                    return array('status' => 'Specified target "' . $target . "' does not exist.");
                }

                // Get the model, and walk to the appropriate path.
                $mdl = $this->getModel();
                foreach (explode('.', $path) as $step) {
                    $mdl = $mdl->{$step};
                }
                // Send via HTTP response the content type.
                $this->response->setContentType('application/json', 'UTF-8');
                // Send via HTTP response the JSON encoded array of the node.
                $this->response->setContent(json_encode($mdl->getNodes()));
            } else {
                throw new UserException('Unsupported request type');
            }
        }
    }

    /**
     * Import data provided by the user in a file upload into ArrayField type
     * nodes in the config.
     *
     * API endpoint:
     *
     * `/api/dnscryptproxy/settings/import`
     *
     * Takes data from POST variables `data` and `target`, validates accordingly,
     * then updates existing objects with the same UUID, or creates new entries
     * then saves the entries into the config.
     *
     * Example usage (Javascript):
     * ```
     * ajaxCall("/api/dnscryptproxy/settings/import",
     *          {'data': import_data,'target': 'allowed_names_internal' },
     *          function(data,status) {...
     * ```
     * The second paramter is the data (array) where `data`, and `target` are
     * defined.
     *
     * No support for `CSVListField` types within an ArrayField type.
     * Attempting currently returns error:
     * ```
     * Error at /usr/local/opnsense/mvc/app/models/OPNsense/Base/FieldTypes/BaseField.php:639
     * It is not yet possible to assign complex types to properties (errno=2)
     * ```
     * This was mostly copied from the firewall plugin.
     */
    public function importAction()
    {
        if ($this->request->isPost()) {
            $this->sessionClose();
            $result = array('import' => 0, 'status' => '');

            // Get target, and data from the POST.
            $data = $this->request->getPost('data');
            $target = $this->request->getPost('target');

            if (! is_null($target)) {  // Only do stuff if target is actually set.
                if (is_array($data)) {  // Only do this if the data we have is an array.
                    if (in_array($target, self::$validGridTargets)) {  // Gotta be on the VIP list.
                        $path = $this->$target;  // Set the path to the class var of the same name as the value of $target.
                    } else {
                        return array('status' => 'Specified target "' . $target . "' does not exist.");
                    }
                    // Get the model for use later. (used for updating records)
                    $mdl = $this->getModel();
                    // Create a second model object that is walked to the last node. (used for new records)
                    $tmp = $mdl;
                    foreach (explode('.', $path) as $step) {
                        $tmp = $tmp->{$step};
                    }
                    // Get a lock on the config.
                    Config::getInstance()->lock();
                    // For each data[n], store it as uuid (string) and its content (array).
                    foreach ($data as $data_uuid => $data_content) {
                        // Reset the node on each iteration.
                        $node = null;
                        // Only do if our content is the correct format.
                        if (is_array($data_content)) {
                            // If the node exists (by UUID), this selects the node.
                            $node = $mdl->getNodeByReference($path . '.' . $data_uuid);
                            // If no node is found, create a new node.
                            if ($node == null) {
                                $node = $tmp->Add();
                            }
                            // Set the new or found node to the content.
                            $node->setNodes($data_content);
                            // Increment the import counter.
                            $result['import'] += 1;
                        }
                    }

                    // Create the uuid mapping for validation messaging.
                    $uuid_mapping = array();
                    // perform validation, record details.
                    foreach ($this->getModel()->performValidation() as $msg) {
                        if (empty($result['validations'])) {
                            $result['validations'] = array();
                        }
                        $parts = explode('.', $msg->getField());
                        $uuid = $parts[count($parts) - 2];
                        $fieldname = $parts[count($parts) - 1];
                        $uuid_mapping[$uuid] = "$uuid";
                        $result['validations'][$uuid_mapping[$uuid] . '.' . $fieldname] = $msg->getMessage();
                    }

                    // possibly use save() from ApiMutableModelControllerBase
                    // only persist when valid import
                    if (empty($result['validations'])) {
                        $result['status'] = 'ok';
                        $this->save();
                    } else {
                        $result['status'] = 'failed';
                        Config::getInstance()->unlock();
                    }
                }
            } else {
                throw new UserException('Unsupported request type');
            }
            // Return messages, either success or failure.
            return $result;
        }
    }

    /**
     * Special function to restore the default sources into the config.
     *
     * **WARNING**: This function **WIPES** ALL existing sources.source entires.
     *
     * API endpoint:
     *
     * `/api/dnscryptproxy/settings/restoreSources`
     *
     * This function will restore the sources back to the ones defined by
     * the application author inlcluded in the example `dnscrypt-proxy.toml`.
     *
     * This is probably not ideal with this data being hardcoded in the source.
     * I'll probably visit this later to see if I can come up with another way.
     *
     * It uses a bit of a janky approach but was the best approach I could
     * figure out with the fewest lines of code, while also being fairly clear
     * about what is happening.
     *
     *  @return array   `addBase()` results
     */
    public function restoreSourcesAction()
    {
        $myController = new ControllerBase();
        $this_form = $myController->getForm('settings');   // Pull in the form data.

        $target = 'sources.source';
        $target_array = $this->searchFields($target, $this_form['tabs']);
        // First we get the current sources to use the UUIDs of each to delete them.
        $sources = $this->searchBase($target_array['path'], $target_array['fields']);

        // Deleting each rows in the sources node. This is inefficient, but negligable as most wont add more than these two anyway.
        foreach ($sources['rows'] as $source) {
            $this->delBase($target_array['path'], $source['uuid']);
        }
        $this->sessionClose();

        // This was the cleanest way I could find to do this since addBase() has a check that there is a POST.
        // So we inject into the POST variable beforehand.
        $_POST['public_resolvers'] = array(
            'enabled' => 1,
            'name' => 'public-resolvers',
            'urls' => 'https://raw.githubusercontent.com/DNSCrypt/dnscrypt-resolvers/master/v3/public-resolvers.md,https://download.dnscrypt.info/resolvers-list/v3/public-resolvers.md',
            'cache_file' => 'public-resolvers.md',
            'minisign_key' => 'RWQf6LRCGA9i53mlYecO4IzT51TGPpvWucNSCh1CBM0QTaLn73Y7GFO3',
            'refresh_delay' => (int) '',  //Have to expicitley define this value as int for validiation, the default setting for this is undefined.
            'prefix' => '',
        );
        $_POST['relays'] = array(
            'enabled' => 1,
            'name' => 'relays',
            'urls' => 'https://raw.githubusercontent.com/DNSCrypt/dnscrypt-resolvers/master/v3/relays.md,https://download.dnscrypt.info/resolvers-list/v3/relays.md',
            'cache_file' => 'relays.md',
            'minisign_key' => 'RWQf6LRCGA9i53mlYecO4IzT51TGPpvWucNSCh1CBM0QTaLn73Y7GFO3',
            'refresh_delay' => 72,
            'prefix' => '',
        );

        // Add our settings, and put the settings into the results variable. Also inefficient.
        $result[1] = $this->addBase('public_resolvers', $target_array['path']);
        $result[2] = $this->addBase('relays', $target_array['path']);
        // Setting our status to ok for SimpleActionButton()
        $result['status'] = 'ok';

        return $result;
    }

    /**
     * Execute a configd command, and return the output in a format consumable
     * by bootgrid.
     *
     * It executes configd with the given commands, and returns JSON for bootrid
     * to consume. It works similar to, and sourced mostly from `searchBase()`,
     * but with some differences and added functionality.
     *
     * Takes most parameters via POST just like *Base() functions.
     *
     * Lots of comments because this function is a bit confusing to start.
     *
     * @param string  $configd_cmd   path to search, relative to this model
     * @param array   $fields        field names to fetch in result
     * @return array output from the configd command
     */
    public function bootgridConfigd($configd_cmd, $fields)
    {
        $backend = new Backend();
        $sort = SORT_ASC;
        $sortBy = array($fields[0]); // set a default column to sort with as the first column defined.
        // Get the items per page, and current page from the GET sent by bootgrid.
        $itemsPerPage = $this->request->get('rowCount', 'int', -1);
        $currentPage = $this->request->get('current', 'int', 1);
        // Set up the sort stuff.
        if ($this->request->has('sort') && is_array($this->request->get('sort'))) {
            $sortBy = array_keys($this->request->get('sort'));
            if ($this->request->get('sort')[$sortBy[0]] == 'desc') {
                $sort = SORT_DESC;
            }
        }
        // Grab the search phrase from the GET call.
        $searchPhrase = $this->request->get('searchPhrase', 'string', '');
        $rows = array();

        $this->sessionClose();
        $result = array('rows' => array());
        $recordIndex = 0;

        // Run the configd command and get the results put into an array. Expects to receive JSON. Maybe add validation later.
        $response = json_decode($backend->configdpRun($configd_cmd), true);

        if (! empty($response)) {
            // Pivot the data to create arrays of each column of data.
            // ex. rows['description'], rows['nolog'], etc.
            // These are used to sort based on column.
            foreach ($response as $item) {
                foreach ($item as $key => $value) {
                    if (! isset($rows[$key])) { // Establish the row if it does not already exist.
                        $rows[$key] = array();
                    }
                    $rows[$key][] = $value;
                }
            }

            // This bit here is taking the desired sort column sent as param
            // and sorting it ASC, or DESC also sent as param.
            // At the same time it's taking the cooresponding index from
            // $reponse and put it in the same position as the sorted row.
            // This sorts by the desired column $rows, and rearranges $response
            // to be in the same order, thus sorting $response by the desired
            // column.
            array_multisort($rows[$sortBy[0]], $sort, $response);
            // ^ This will throw an error if an element is missing from one of
            // the entries. Like description being missing on static server entries.
            // $rows will be shorter than $response. This has been mitigated by
            // adding an empty description field in the script.

            // This was copied almost wholesale from UIModelGrid(), if it ain't broke.
            // I added a boolean check in the search bit.
            foreach ($response as $row) {
                // if a search phrase is provided, use it to search in all requested fields
                if (! empty($searchPhrase)) {
                    $searchFound = false;

                    foreach ($fields as $fieldname) {  //Iterate through the field list provided as function param.
                        // For each field in the row, we check to see if the searchPhrase is found, one at a time.
                        // Catch a corner case where a row is missing from the data, test for null (manually defined servers have no description).
                        $field = (isset($row[$fieldname]) ? $row[$fieldname] : null);
                        if (! is_null($field)) {
                            if (is_array($field)) {  // Only do if this is an array
                                foreach ($field as $fieldvalue) {  //Iterate through each value of this array and evaluate.
                                    if (strtolower($searchPhrase) == strtolower($fieldname)) {  // If the field name happens to match the searchPhrase, we might be a boolean.
                                        if (is_int($fieldvalue) && ($fieldvalue == 0 || $fieldvalue == 1)) {  // Guess if the field is a boolean.
                                            if ($fieldvalue == 1) {
                                                # Guessing that int(1) will mean true.
                                                # Have to use 0 or 1 here because opensense_bootgrid_plugin.js uses that instead of true/false.
                                                $searchFound = true;

                                                break;
                                            }
                                            // $fieldvalue is 0, so we should abort the rest of the columns.
                                            // We assume that we're searching for a bool, and if this is 0
                                            // then this row should not be included. Do not set $searchFound,
                                            // but still break out of the loop, effectively skipping the row.
                                            // Not ideal as it prevents string searching for a value
                                            // the same as a field name in the rest of the colums.
                                            // Need some special syntax to signify a bool search via $searchPhrase.
                                            break;
                                        }
                                    } elseif (strpos(strtolower($fieldvalue), strtolower($searchPhrase)) !== false) {
                                        $searchFound = true;

                                        break;
                                    }
                                }
                            } else {
                                if (strtolower($searchPhrase) == strtolower($fieldname)) {  // If the field name happens to match the searchPhrase, we might be a boolean.
                                    if (is_int($field) && ($field == 0 || $field == 1)) {  // Guess if the field is a boolean.
                                        if ($field == 1) {
                                            # Guessing that int(1) will mean true.
                                            $searchFound = true;

                                            break;
                                        }
                                        // $field is 0, so we should abort the rest of the columns.
                                        // We assume that we're searching for a bool, and if this is 0
                                        // then this row should not be included. Do not set $searchFound,
                                        // but still break out of the loop, effectively skipping the row.
                                        // Not ideal as it prevents string searching for a value
                                        // the same as a field name in the rest of the colums.
                                        // Need some special syntax to signify a bool search via $searchPhrase.
                                        break;
                                    }
                                } elseif (strpos(strtolower($field), strtolower($searchPhrase)) !== false) {
                                    $searchFound = true;

                                    break;
                                }
                            }
                        }
                    }
                } else {
                    // If there is no search phrase, we assume all rows are relevent.
                    $searchFound = true;
                }

                // if result is relevant, count total and add (max number of) items to result.
                // $itemsPerPage = -1 is used as wildcard for "all results"
                if ($searchFound) {
                    if (
                        (count($result['rows']) < $itemsPerPage &&
                        $recordIndex >= ($itemsPerPage * ($currentPage - 1)) || $itemsPerPage == -1)
                    ) {
                        $result['rows'][] = $row;
                    }
                    $recordIndex++;
                }
            }
        }
        // We're all done, so now return what we have in a way bootgrid expects.
        $result['rowCount'] = count($result['rows']);
        $result['total'] = $recordIndex;
        $result['current'] = (int) $currentPage;
        $result['status'] = 'ok';
        $result['POST'] = $_POST;
        $result['params'] = array('configd_cmd' => $configd_cmd, 'fields' => $fields);
        $result['response'] = $response;

        return $result;
    }
}
