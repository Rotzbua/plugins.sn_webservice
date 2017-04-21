<?php

namespace ScoutNet\Api;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2017 Stefan "Mütze" Horst <muetze@scoutnet.de>, ScoutNet
 *
 *  All rights reserved
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use ScoutNet\Api\Exceptions\ScoutNetException;
use ScoutNet\Api\Exceptions\ScoutNetExceptionMissingConfVar;
use ScoutNet\Api\Helpers\CacheHelper;
use ScoutNet\Api\Helpers\ConverterHelper;

use ScoutNet\Api\Models\Event;

use ScoutNet\Api\Helpers\AesHelper;
use ScoutNet\Api\Helpers\JsonRPCClientHelper;

class ScoutnetApi {
    const UNSECURE_START_IV = '1234567890123456';

    const ERROR_AUTH_BROKEN = 1491935269;
    const ERROR_WRONG_PROVIDER = 1491935295;
    const ERROR_AUTH_TOO_OLD = 1491935476;
    const ERROR_AUTH_EMPTY = 1491935505;

    const ERROR_MISSING_API_KEY = 1491938183;
    const ERROR_MISSING_API_USER = 1492695814;

    var $SN = null;

    var $snData;

    private $provider = null;
    private $aes_key = null;
    private $aes_iv = null;

    private $api_user = null;
    private $api_key = null;

    private $login_url = null;

    private $cache = null;
    private $converter = null;

    /**
     * Construct the ScoutNet API. To read you need not set anything. For Writing access you must set the
     * provider, aes_key and aes_iv values
     *
     * @param string $api_url   if not set use the default value
     * @param string $login_url use default value
     * @param string $provider  set the provider name to write content
     * @param string $aes_key   set aes key for the provider
     * @param string $aes_iv    set aes iv for the provider
     */
    public function __construct($api_url = "https://www.scoutnet.de/jsonrpc/server.php", $login_url = 'https://www.scoutnet.de/community/scoutnetConnect.html', $provider = '', $aes_key = '', $aes_iv = '') {
        $this->SN = new JsonRPCClientHelper($api_url);

        $this->set_scoutnet_connect_data($login_url, $provider, $aes_key, $aes_iv);

        $this->cache = new CacheHelper();
        $this->converter = new ConverterHelper($this->cache);
    }

    /**
     * We want to use curl, so we need to set it in the api
     *
     * @param string $curlProxyServer
     * @param string $curlProxyTunnel
     * @param string $curlProxyUserPass
     */
    public function setUseCurl($curlProxyServer = null, $curlProxyTunnel = null, $curlProxyUserPass = null){
        $this->SN->setUseCurl($curlProxyServer, $curlProxyTunnel, $curlProxyUserPass);
    }

    /**
     * Use this function to set the Parameter to be able to use the write API (if not set in the constructor)
     *
     * @param string $login_url
     * @param string $provider
     * @param string $aes_key
     * @param string $aes_iv
     *
     * @throws \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     */
    public function set_scoutnet_connect_data($login_url = null, $provider = '', $aes_key = '', $aes_iv = '') {
        if ($login_url == null) {
            $login_url = 'https://www.scoutnet.de/community/scoutnetConnect.html';
        }
        $provider = trim($provider);

        $this->login_url = $login_url;
        $this->provider = $provider;
        $this->aes_key = $aes_key;
        $this->aes_iv = $aes_iv;

        // if one is set all must be set correct
        if ($provider != '' || $aes_key !== '' || $aes_iv !== '') {
            $this->_check_for_all_configValues();
        }
    }

    /**
     * checks if ScoutNet connect Values are set correctly
     *
     * @throws \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     */
    private function _check_for_all_configValues() {
        if (trim($this->aes_key) == '' || strlen($this->aes_key) != 32) {
            throw new ScoutnetExceptionMissingConfVar('aes_key');
        }
        if (trim($this->aes_iv) == '' || strlen($this->aes_iv) != 16) {
            throw new ScoutnetExceptionMissingConfVar('aes_iv');
        }
        if (trim($this->login_url) == '') {
            throw new ScoutnetExceptionMissingConfVar('login_url');
        }
        if (trim($this->provider) == '') {
            throw new ScoutnetExceptionMissingConfVar('provider');
        }
    }

    public function loginUser($api_user, $api_key) {
        $this->api_user = $api_user;
        $this->api_key = $api_key;
    }

    private function _check_login() {
        if (trim($this->api_user) == '') {
            throw new ScoutNetExceptionMissingConfVar('api_user', self::ERROR_MISSING_API_USER);
        }
        if (trim($this->api_key) == '' || strlen($this->api_key) != 32) {
            throw new ScoutnetExceptionMissingConfVar('api_key', self::ERROR_MISSING_API_KEY);
        }
    }

    /**
     * @param int[]|int $ids   SSIDs of Structures to request data from
     * @param array     $query Filter Query for Request
     *
     * @return mixed
     */
    public function load_data_from_scoutnet($ids, $query) {
        $res = $this->SN->get_data_by_global_id($ids, $query);

        return $res;
    }

    /**
     * Load Events for Structure
     *
     * @param int[]|int $ids    SSIDs of Kalenders to request Events from
     * @param array     $filter only find Events matching this filter
     *
     * @return  Event[]
     */
    public function get_events_for_global_id_with_filter($ids, $filter) {
        $events = [];

        foreach ($this->load_data_from_scoutnet($ids, array('events' => $filter)) as $record) {
            if ($record['type'] === 'user') {
                // convert to User and save to cache
                $this->converter->convertApiToUser($record['content']);
            } elseif ($record['type'] === 'stufe') {
                // convert to Stufe and save to cache
                $this->converter->convertApiToStufe($record['content']);
            } elseif ($record['type'] === 'kalender') {
                // convert to Structure and save to cache
                $this->converter->convertApiToStructure($record['content']);
            } elseif ($record['type'] === 'event') {
                $events[] = $this->converter->convertApiToEvent($record['content']);
            }
        }
        return $events;
    }

    /**
     * Load Index Elements for this Structure Element
     *
     * @param int[]|int $ids    SSIDs to find Index Elements for
     * @param array     $filter Filter for Elements
     *
     * @return \ScoutNet\Api\Models\Index[]
     */
    public function get_index_for_global_id_with_filter($ids, $filter) {
        $indexes = array();
        foreach ($this->load_data_from_scoutnet($ids, array('index' => $filter)) as $record) {

            if ($record['type'] === 'index') {
                $index = $this->converter->convertApiToIndex($record['content']);

                $indexes[$index->getUid()] = $index;
            }
        }

        return $indexes;
    }

    /**
     * Load Events with this IDs from the given Kalenders
     *
     * @param int[]|int $ids       SSIDs to load events for
     * @param int[]     $event_ids IDs of Events to load
     *
     * @return \ScoutNet\Api\Models\Event[]
     */
    public function get_events_with_ids($ids, $event_ids) {
        return $this->get_events_for_global_id_with_filter($ids, array('event_ids' => $event_ids));
    }

    /**
     * @param int[]|int $ids SSIDs to load Kalenders for
     *
     * @return \ScoutNet\Api\Models\Structure[]
     */
    public function get_kalender_by_global_id($ids) {
        $kalenders = array();
        foreach ($this->load_data_from_scoutnet($ids, array('kalenders' => array())) as $record) {
            if ($record['type'] === 'kalender') {
                $kalenders[] = $this->converter->convertApiToStructure($record['content']);
            }
        }

        return $kalenders;
    }

    /**
     * Use this function to write event to scoutnet
     *
     * @param Event  $event   Event Object
     *
     * @return mixed
     */
    public function write_event(Event $event) {
        $type = 'event';
        $id = $event->getUid();
        $apiData = $this->converter->convertEventToApi($event);

        return $this->converter->convertApiToEvent($this->write_object($type, $id, $apiData));
    }

    /**
     * @param string $type    Type of Object to write; The API only works with events
     * @param int    $id      ID of object to update/ create
     * @param array  $data    Object data as Array
     *
     * @return mixed
     */
    public function write_object($type, $id, $data) {
        $auth = $this->_generate_auth($type . $id . json_encode($data) . $this->api_user);

        return $this->SN->setData($type, $id, $data, $this->api_user, $auth);
    }

    public function delete_event($ssid, $id) {
        $type = 'event';
        $auth = $this->_generate_auth($type . $ssid . $id . $this->api_user);

        return $this->SN->deleteObject($type, $ssid, $id, $this->api_user, $auth);
    }

    /**
     * This Function returns if a given user has write permissions on a specified Kalendar.
     *
     * @param int    $ssid    SSID of Calendar
     *
     * @return int State of Write Rights
     */
    public function has_write_permission_to_calender($ssid) {
        $permission = $this->get_permissions_of_type_for_structure('event', $ssid);

        return $permission->getState();
    }

    /**
     * @param string $type    Type of Permissions
     * @param int    $ssid    SSID of Structure
     *
     * @return \ScoutNet\Api\Models\Permission
     */
    public function get_permissions_of_type_for_structure($type, $ssid) {
        $auth = $this->_generate_auth( $type . $ssid . $this->api_user);

        $right = $this->SN->checkPermission($type, $ssid, $this->api_user, $auth);
        $right['type'] = $type;

        $permission = $this->converter->convertApiToPermission($right);
        return $permission;
    }

    /**
     * Request Write Permission for a specified User
     *
     * @param int    $ssid    SSID of Structure
     *
     * @return mixed
     */
    public function request_write_permissions_for_calender($ssid) {
        $type = 'event';

        return $this->request_permissions_of_type_for_structure($type, $ssid);
    }

    /**
     * Request Permission of given type for a specified User
     *
     * @param string $type    Type of Permission
     * @param int    $ssid    SSID of Structure
     *
     * @return mixed
     */
    public function request_permissions_of_type_for_structure($type, $ssid) {
        $auth = $this->_generate_auth($type . $ssid . $this->api_user);

        return $this->SN->requestPermission($type, $ssid, $this->api_user, $auth);
    }


    /**
     * returns the ScoutNet Connect Button in formated HTML
     *
     * @param string $returnUrl
     * @param bool   $requestApiKey
     * @param string $imageURL
     * @param string $lang
     *
     * @return string
     */
    public function get_scoutnet_connect_login_button($returnUrl = '', $requestApiKey = false, $imageURL = 'https://www.scoutnet.de/images/scoutnetConnect.png', $lang = 'de') {
        $this->_check_for_all_configValues();
        $button = '<form action="' . $this->login_url . '" id="scoutnetLogin" method="post" target="_self">' . "\n";

        $button .= $returnUrl == '' ? '' : "    " . '<input type="hidden" name="redirect_url" value="' . $returnUrl . '" />' . "\n";
        $button .= "    " . '<input type="hidden" name="lang" value="' . $lang . '"/>' . "\n";
        $button .= "    " . '<input type="hidden" name="provider" value="' . $this->provider . '" />' . "\n";
        $button .= $requestApiKey ? ("    " . '<input type="hidden" name="createApiKey" value="1" />' . "\n") : '';

        $button .= "    " . '<a href="#" onclick="document.getElementById(\'scoutnetLogin\').submit(); return false;">' . "\n";

        $button .= "        " . '<img src="' . $imageURL . '" title="Login with Scoutnet" alt="scoutnet Login"/>' . "\n";
        $button .= "    " . '</a>' . "\n";

        $button .= '</form>';

        return $button;
    }

    /**
     * Extract the Api key from _GET variables. This Function is used in after the ScoutNet Connect Login.
     *
     * @return string[]|bool
     * @throws \ScoutNet\Api\Exceptions\ScoutnetException
     */
    public function getApiKeyFromData() {
        // we already extracted the Data so do not do it twice
        if (isset($this->snData)) {
            return $this->snData;
        }

        // there is no _GET['auth'] we cannot extract it
        if (!isset($_GET['auth'])) {
            return false;
        }

        // decode Input
        $base64 = base64_decode(strtr($_GET['auth'], '-_~', '+/='));

        if (trim($base64) == "")
            throw new ScoutnetException('AUTH is empty', self::ERROR_AUTH_EMPTY);

        // check if we have the apropiate Values Configed (AES_Key and AES_IV etc.)
        $this->_check_for_all_configValues();

        // Use AES with CBC and the set IV and KEY
        $aes = new AESHelper($this->aes_key, "CBC", $this->aes_iv);

        // decrypt data and drop first Block, since it only contains Random to fix static IV
        $json = substr($aes->decrypt($base64), strlen($this->aes_iv));
        $data = json_decode($json, true);

        $md5 = $data['md5'];
        unset($data['md5']);
        $sha1 = $data['sha1'];
        unset($data['sha1']);

        // the hashes are generated without the hashes themself
        if (md5(json_encode($data)) != $md5) {
            throw new ScoutnetException('Could not verify AUTH', self::ERROR_AUTH_BROKEN);
        }

        if (sha1(json_encode($data)) != $sha1) {
            throw new ScoutnetException('Could not verify AUTH', self::ERROR_AUTH_BROKEN);
        }

        if (time() - $data['time'] > 3600) {
            throw new ScoutnetException('AUTH is too old', self::ERROR_AUTH_TOO_OLD);
        }

        $your_domain = $this->provider;

        if ($data['your_domain'] !== $your_domain)
            throw new ScoutNetException('AUTH for wrong provider', self::ERROR_WRONG_PROVIDER);

        $this->snData = $data;

        return $data;
    }

    /**
     * This function generates the Auth hash to verify request
     *
     * @param string $checkValue Value to sign with Api Key
     *
     * @return string
     */
    private function _generate_auth($checkValue) {
        $this->_check_login();

        $aes = new AESHelper($this->api_key, "CBC", self::UNSECURE_START_IV);

        $auth = array(
            'sha1' => sha1($checkValue),
            'md5' => md5($checkValue),
            'time' => time(),
        );
        $auth = json_encode($auth);

        // Generate random first block, which is deleted on the reciever side.
        // This is done since we use the same iv all the time.
        $first_block = '';
        for ($i = 0; $i < 16; $i++) {
            $first_block .= chr(rand(0, 255));
        }

        return strtr(base64_encode($aes->encrypt($first_block . $auth)), '+/=', '-_~');
    }
}

