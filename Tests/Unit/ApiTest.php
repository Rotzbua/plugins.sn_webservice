<?php

namespace ScoutNet\Api\Tests;
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

use PHPUnit\Framework\TestCase;
use ScoutNet\Api\Exceptions\ScoutNetExceptionMissingConfVar;
use ScoutNet\Api\Helpers\JsonRPCClientHelper;
use \Exception;
use ScoutNet\Api\Helpers\AesHelper;
use ScoutNet\Api\Models\Categorie;
use ScoutNet\Api\Models\Event;
use ScoutNet\Api\Models\Permission;
use ScoutNet\Api\Models\Structure;
use ScoutNet\Api\Models\User;
use ScoutNet\Api\ScoutnetApi;

/**
 * @covers \ScoutNet\Api\ScoutnetApi
 * @covers \ScoutNet\Api\Exceptions\ScoutNetExceptionMissingConfVar
 * @covers \ScoutNet\Api\Exceptions\ScoutNetException
 */
final class ApiTest extends TestCase {
    const AES_KEY = "12345678901234567890123456789012";
    const AES_IV = "1234567890123456";

    const API_PROVIDER = "phpunit";
    const API_USER = "phpunit";
    const API_KEY = "12345678901234567890123456789012";

    const MOCKED_TIME_VALUE = 1;
    const MOCKED_RAND_VALUE = 4;

    private $sn = null;

    public function __construct($name = null, array $data = array(), $dataName = '') {
        parent::__construct($name, $data, $dataName);
        $this->sn = new ScoutnetApi();
        $jsonRPCClientHelperMock = new JsonRPCClientHelperMock('https://www.scoutnet.de/jsonrpc/server.php', false, CACHE_DIR); // load data to be mocked from $url

        // inject RPCClientMock
        $objectReflection = new \ReflectionObject($this->sn);
        $property = $objectReflection->getProperty('SN');
        $property->setAccessible(true);
        $property->setValue($this->sn, $jsonRPCClientHelperMock);
    }

    /**
     * set correct Write Credentials, so we can test the wrong Credentials too
     */
    private function _setCorrectWriteCredentials() {
        $this->sn->set_scoutnet_connect_data('https://www.scoutnet.de/community/scoutnetConnect.html', self::API_PROVIDER, self::AES_KEY, self::AES_IV);
    }

    /**
     * Generates the Auth for a given time, provider, api key etc..
     * So we can check if broken api keys are correcly detected.
     *
     * @param int    $time        Time for the Auth
     * @param string $provider    Provider in the Auth
     * @param string $user        User in the Auth
     * @param string $api_key     Api Key in the Auth
     * @param bool   $broken_md5  bool, if we generate a broken md5 hash
     * @param bool   $broken_sha1 bool, if we generate a broken sha1 hash
     *
     * @return string AUTH
     */
    private function _generateAuth($time = self::MOCKED_TIME_VALUE, $provider = self::API_PROVIDER, $user = self::API_USER, $api_key = self::API_KEY, $broken_md5 = false, $broken_sha1 = false) {
        $aes = new AesHelper(self::AES_KEY, "CBC", self::AES_IV);

        $content = [];
        $content['time'] = $time;
        $content['your_domain'] = $provider;
        $content['user'] = $user;
        $content['api_key'] = $api_key;

        // generate temp json to build hashes from
        $json = json_encode($content);

        $content['md5'] = $broken_md5 ? "12345678901234567890123456789012" : md5($json);
        $content['sha1'] = $broken_sha1 ? "1234567890123456789012345678901234567890" : sha1($json);

        $json = json_encode($content);

        $data = base64_encode(self::AES_IV . $aes->encrypt($json));

        return $data;
    }

    public function testCanBeCreated() {
        $this->assertInstanceOf(
            ScoutnetApi::class,
            new ScoutnetApi()
        );
    }

    public function testGetEventsForGlobalId() {

        $events = $this->sn->get_events_for_global_id_with_filter(4, array('limit' => '1', 'before' => '12.01.2012'));

        $this->assertEquals(1, count($events), "got more than one event");
        $this->assertEquals(4, $events[0]->getStructure()->getUid(), "wrong kalender id for event");
    }

    public function testGetKalenderElements() {
        $kalender = $this->sn->get_kalender_by_global_id('4');
        $this->assertEquals(1, count($kalender), "got more than one kalender");
        $this->assertEquals(4, $kalender[0]->getUid(), "wrong kalender id returned");
    }

    public function testGetIndexElements() {
        $indexes = $this->sn->get_index_for_global_id_with_filter(4, array('deeps' => '2'));

        $this->assertGreaterThan(13, count($indexes), 'to few structures returned, as of writing there were 113');

        $diozese = $indexes['4']; // diozese Köln
        $this->assertGreaterThan(5, count($diozese->getChildren()), 'less than 5 Bezirke, as of writing there were 12');

        $bezirk = $indexes['17']; // bezirk erft
        $this->assertGreaterThan(5, count($bezirk->getChildren()), "less than 5 Stämme, as of writing there were 12");
    }


    public function testException() {
        $ext = new ScoutNetExceptionMissingConfVar('test', 23);

        $this->assertEquals(23, $ext->getCode());
        $this->assertEquals("Missing 'test'. Please Contact your Admin to enter a valid credentials for ScoutNet Connect. You can request them via <a href=\"mailto:scoutnetconnect@scoutnet.de\">scoutnetConnect@ScoutNet.de</a>.", $ext->getMessage());
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     * @expectedExceptionMessageRegExp /^Missing 'aes_key'.*$/
     */
    public function testSetScoutnetConnectDataFailureAES_KEY() {
        $this->sn->set_scoutnet_connect_data('https://www.scoutnet.de/community/scoutnetConnect.html', self::API_PROVIDER, '', self::AES_IV);
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     * @expectedExceptionMessageRegExp /^Missing 'aes_iv'.*$/
     */
    public function testSetScoutnetConnectDataFailureAES_IV() {
        $this->sn->set_scoutnet_connect_data('https://www.scoutnet.de/community/scoutnetConnect.html', self::API_PROVIDER, self::AES_KEY, '');
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     * @expectedExceptionMessageRegExp /^Missing 'provider'.*$/
     */
    public function testSetScoutnetConnectDataFailiureProvider() {
        $this->sn->set_scoutnet_connect_data('https://www.scoutnet.de/community/scoutnetConnect.html', '', self::AES_KEY, self::AES_IV);
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     * @expectedExceptionMessageRegExp /^Missing 'login_url'.*$/
     */
    public function testSetScoutnetConnectDataFailiureLoginurl() {
        $this->sn->set_scoutnet_connect_data('  ', self::API_PROVIDER, self::AES_KEY, self::AES_IV);
    }

    public function testSetScoutnetConnectDataDefaultValues() {
        $this->sn->set_scoutnet_connect_data();

        $objectReflection = new \ReflectionObject($this->sn);
        $login_url = $objectReflection->getProperty('login_url');
        $login_url->setAccessible(true);

        $this->assertEquals('https://www.scoutnet.de/community/scoutnetConnect.html', $login_url->getValue($this->sn));
    }

    public function testScoutNetConnectLogin() {
        $this->_setCorrectWriteCredentials();

        $connect_button = $this->sn->get_scoutnet_connect_login_button('http://localhost/testclient.php', true, 'https://www.scoutnet.de/images/scoutnetConnect.png', 'de');
        $expected_connect_button = file_get_contents(CACHE_DIR . "ConnectButton.html");


        $this->assertEquals($expected_connect_button, $connect_button);
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     * @expectedExceptionCode 1492695814
     */
    public function testSetLoginDetailsUserEmpty() {
        $this->sn->loginUser('', self::API_KEY);
        $objectReflection = new \ReflectionObject($this->sn);
        $method = $objectReflection->getMethod('_check_login');
        $method->setAccessible(true);

        $method->invokeArgs($this->sn, []);
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     * @expectedExceptionCode 1491938183
     */
    public function testSetLoginDetailsApiKeyEmpty() {
        $this->sn->loginUser(self::API_USER, '');
        $objectReflection = new \ReflectionObject($this->sn);
        $method = $objectReflection->getMethod('_check_login');
        $method->setAccessible(true);

        $method->invokeArgs($this->sn, []);
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     * @expectedExceptionCode 1491938183
     */
    public function testSetLoginDetailsApiKeyWrongLength() {
        $this->sn->loginUser(self::API_USER, 'abc');
        $objectReflection = new \ReflectionObject($this->sn);
        $method = $objectReflection->getMethod('_check_login');
        $method->setAccessible(true);

        $method->invokeArgs($this->sn, []);
    }

    public function testSetLoginDetails() {
        $this->sn->loginUser(self::API_USER, self::API_KEY);
        $objectReflection = new \ReflectionObject($this->sn);
        $method = $objectReflection->getMethod('_check_login');
        $method->setAccessible(true);

        $method->invokeArgs($this->sn, []);
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetExceptionMissingConfVar
     * @expectedExceptionCode 1491938183
     */
    public function testGenerateAuthWithoutApiKey() {
        $this->sn->loginUser(self::API_USER, 'wrongApiKey');
        // call to private function
        $objectReflection = new \ReflectionObject($this->sn);
        $method = $objectReflection->getMethod('_generate_auth');
        $method->setAccessible(true);

        $method->invokeArgs($this->sn, ['test']);
    }

    public function testGenerateAuthWithCorrectApiKey() {
        $this->sn->loginUser(self::API_USER, self::API_KEY);

        // call to private function
        $objectReflection = new \ReflectionObject($this->sn);
        $method = $objectReflection->getMethod('_generate_auth');
        $method->setAccessible(true);

        $auth = $method->invokeArgs($this->sn, ['test']);

        $this->assertEquals("cGJcjkxp40dKZP6Cf8QfpCiqDcXTRmrD50zdjtjBATWDeSMbj0Ro0etFtMJBASd-NBn41PC-y6IvI-h2QejUNm7g9IVpaSJj1_ibUSDoSwVNTdS_c0RSem8XyO-gTrl78gVH0AnJ13B9PUDj_mMsuhmTe_YWh-DuQ-x4ZTlM3IQ~", $auth);
    }

    public function testGetApiKeyFromDataWithNoAuth() {
        $this->_setCorrectWriteCredentials();

        $data = $this->sn->getApiKeyFromData();

        $this->assertEquals(false, $data);
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetException
     * @expectedExceptionMessageRegExp /^AUTH is empty$/
     */
    public function testGetApiKeyFromDataWithEmptyAuth() {
        $this->_setCorrectWriteCredentials();

        // we mocked the time for the API
        $_GET['auth'] = 'AAAA';
        $this->sn->getApiKeyFromData();
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetException
     * @expectedExceptionMessageRegExp /^Could not verify AUTH$/
     */
    public function testGetApiKeyFromDataWithBrokenMd5() {
        $this->_setCorrectWriteCredentials();

        // we mocked the time for the API
        $_GET['auth'] = $this->_generateAuth(self::MOCKED_TIME_VALUE, self::API_PROVIDER, self::API_USER, self::API_KEY, true, false);
        $this->sn->getApiKeyFromData();
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetException
     * @expectedExceptionMessageRegExp /^Could not verify AUTH$/
     */
    public function testGetApiKeyFromDataWithBrokenSha1() {
        $this->_setCorrectWriteCredentials();

        // we mocked the time for the API
        $_GET['auth'] = $this->_generateAuth(self::MOCKED_TIME_VALUE, self::API_PROVIDER, self::API_USER, self::API_KEY, false, true);
        $this->sn->getApiKeyFromData();
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetException
     * @expectedExceptionMessageRegExp /^AUTH is too old$/
     */
    public function testGetApiKeyFromDataWithExpiredTime() {
        $this->_setCorrectWriteCredentials();

        // we mocked the time for the API
        $_GET['auth'] = $this->_generateAuth(\ScoutNet\Api\time() - 5000);
        $this->sn->getApiKeyFromData();
    }

    /**
     * @expectedException \ScoutNet\Api\Exceptions\ScoutnetException
     * @expectedExceptionMessageRegExp /^AUTH for wrong provider$/
     */
    public function testGetApiKeyFromDataWithWrongProvider() {
        $this->_setCorrectWriteCredentials();

        // we mocked the time for the API
        $_GET['auth'] = $this->_generateAuth(self::MOCKED_TIME_VALUE, 'wrongProvider');
        $this->sn->getApiKeyFromData();
    }

    public function testGetApiKeyFromData() {
        $this->_setCorrectWriteCredentials();

        // we mocked the time for the API
        $_GET['auth'] = $this->_generateAuth();

        // this function gets the data from $_GET['auth']
        $data = $this->sn->getApiKeyFromData();

        $scoutnetUser = $data['user'];
        $api_key = $data['api_key'];

        $this->assertEquals(self::API_USER, $scoutnetUser);
        $this->assertEquals(self::API_KEY, $api_key);
    }

    public function testGetApiKeyFromDataCheckCache() {
        $this->_setCorrectWriteCredentials();

        // we mocked the time for the API
        $_GET['auth'] = $this->_generateAuth();

        $first_run = $this->sn->getApiKeyFromData();
        $second_run = $this->sn->getApiKeyFromData();

        $this->assertEquals($first_run, $second_run);
    }

    public function testWritePermissions() {
        $this->sn->loginUser(self::API_USER, self::API_KEY);
        $rights = $this->sn->has_write_permission_to_calender(1);

        $this->assertEquals(Permission::AUTH_WRITE_ALLOWED, $rights);
    }

    public function testWritePermissionsNoAuth() {
        $this->sn->loginUser(self::API_USER, self::API_KEY);
        $rights = $this->sn->has_write_permission_to_calender(2);

        $this->assertEquals(Permission::AUTH_NO_RIGHT, $rights);
    }

    public function testWritePermissionsPending() {
        $this->sn->loginUser(self::API_USER, self::API_KEY);
        $rights = $this->sn->has_write_permission_to_calender(3);

        $this->assertEquals(Permission::AUTH_REQUEST_PENDING, $rights);
    }

    public function testRequestPermissionWorking() {
        $this->sn->loginUser(self::API_USER, self::API_KEY);
        // ask for rights
        $ret = $this->sn->request_write_permissions_for_calender(1);

        $this->assertEquals(Permission::AUTH_REQUESTED, $ret['code']);
    }

    public function testRequestPermissionAlreadyRequested() {
        $this->sn->loginUser(self::API_USER, self::API_KEY);
        // ask for rights
        $ret = $this->sn->request_write_permissions_for_calender(2);

        $this->assertEquals(Permission::AUTH_REQUEST_PENDING, $ret['code']);
    }

    public function testRequestSingleEvent() {

        $cat = new Categorie();
        $cat->setUid(1);
        $cat->setText('Sonstiges');

        $cat858 = new Categorie();
        $cat858->setUid(858);
        $cat858->setText('AGMedien');
        $cat4 = new Categorie();
        $cat4->setUid(4);
        $cat4->setText('Aktion');
        $cat882 = new Categorie();
        $cat882->setUid(882);
        $cat882->setText('Bausteinwochenende');
        $cat7 = new Categorie();
        $cat7->setUid(7);
        $cat7->setText('Bezirksleitung');
        $cat12 = new Categorie();
        $cat12->setUid(12);
        $cat12->setText('Diözesanleitung');
        $cat3 = new Categorie();
        $cat3->setUid(3);
        $cat3->setText('Fahrt/Lager');
        $cat105 = new Categorie();
        $cat105->setUid(105);
        $cat105->setText('Freunde und Förderer');
        $cat5 = new Categorie();
        $cat5->setUid(5);
        $cat5->setText('Gemeinde');
        $cat11 = new Categorie();
        $cat11->setUid(11);
        $cat11->setText('Gremium/AK');
        $cat182 = new Categorie();
        $cat182->setUid(182);
        $cat182->setText('Gruppenstunde');
        $cat810 = new Categorie();
        $cat810->setUid(810);
        $cat810->setText('Jamb');
        $cat43 = new Categorie();
        $cat43->setUid(43);
        $cat43->setText('Leiterrunde');
        $cat125 = new Categorie();
        $cat125->setUid(125);
        $cat125->setText('Ranger & Rover');
        $cat59 = new Categorie();
        $cat59->setUid(59);
        $cat59->setText('Ranger/Rover');
        $cat10 = new Categorie();
        $cat10->setUid(10);
        $cat10->setText('Schulung/Kurs');
        $cat2 = new Categorie();
        $cat2->setUid(2);
        $cat2->setText('Stamm');
        $cat9 = new Categorie();
        $cat9->setUid(9);
        $cat9->setText('Stufenkonferenz');
        $cat881 = new Categorie();
        $cat881->setUid(881);
        $cat881->setText('Teamer Starter Training');
        $cat38 = new Categorie();
        $cat38->setUid(38);
        $cat38->setText('Truppstunde');
        $cat6 = new Categorie();
        $cat6->setUid(6);
        $cat6->setText('Vorstände');

        $cat886 = new Categorie();
        $cat886->setUid(886);
        $cat886->setText('Vorgruppe');
        $cat16 = new Categorie();
        $cat16->setUid(16);
        $cat16->setText('Wölflinge');
        $cat17 = new Categorie();
        $cat17->setUid(17);
        $cat17->setText('Jungpfadfinder');
        $cat18 = new Categorie();
        $cat18->setUid(18);
        $cat18->setText('Pfadfinder');
        $cat19 = new Categorie();
        $cat19->setUid(19);
        $cat19->setText('Rover');
        $cat20 = new Categorie();
        $cat20->setUid(20);
        $cat20->setText('Leiter');
        $cat476 = new Categorie();
        $cat476->setUid(476);
        $cat476->setText('Einstieg Schritt 1');
        $cat179 = new Categorie();
        $cat179->setUid(179);
        $cat179->setText('Einstieg Schritt 2');
        $cat331 = new Categorie();
        $cat331->setUid(331);
        $cat331->setText('Baustein 1 a');
        $cat330 = new Categorie();
        $cat330->setUid(330);
        $cat330->setText('Baustein 1 b');
        $cat477 = new Categorie();
        $cat477->setUid(477);
        $cat477->setText('Baustein 1 c');
        $cat865 = new Categorie();
        $cat865->setUid(865);
        $cat865->setText('Baustein 1 d');
        $cat478 = new Categorie();
        $cat478->setUid(478);
        $cat478->setText('Baustein 2 a');
        $cat479 = new Categorie();
        $cat479->setUid(479);
        $cat479->setText('Baustein 2 b');
        $cat333 = new Categorie();
        $cat333->setUid(333);
        $cat333->setText('Baustein 2 c');
        $cat480 = new Categorie();
        $cat480->setUid(480);
        $cat480->setText('Baustein 2 d');
        $cat820 = new Categorie();
        $cat820->setUid(820);
        $cat820->setText('Baustein 2 e');
        $cat332 = new Categorie();
        $cat332->setUid(332);
        $cat332->setText('Baustein 3 a');
        $cat328 = new Categorie();
        $cat328->setUid(328);
        $cat328->setText('Baustein 3 b');
        $cat481 = new Categorie();
        $cat481->setUid(481);
        $cat481->setText('Baustein 3 c');
        $cat483 = new Categorie();
        $cat483->setUid(483);
        $cat483->setText('Baustein 3 e');
        $cat484 = new Categorie();
        $cat484->setUid(484);
        $cat484->setText('Baustein 3 f');
        $cat485 = new Categorie();
        $cat485->setUid(485);
        $cat485->setText('Woodbadgekurs');
        $cat36 = new Categorie();
        $cat36->setUid(36);
        $cat36->setText('Ausbildungstagung');
        $cat486 = new Categorie();
        $cat486->setUid(486);
        $cat486->setText('Modulleitungstraining (MLT)');
        $cat701 = new Categorie();
        $cat701->setUid(701);
        $cat701->setText('Teamer-Training I');
        $cat702 = new Categorie();
        $cat702->setUid(702);
        $cat702->setText('Teamer-Training II');
        $cat489 = new Categorie();
        $cat489->setUid(489);
        $cat489->setText('Assistent Leader Training (ALT)');
        $cat897 = new Categorie();
        $cat897->setUid(897);
        $cat897->setText('Fort-/Weiterbildung');

        $structure = new Structure();
        $structure->setUid('4');
        $structure->setEbene('Diözese');
        $structure->setName('Köln');
        $structure->setVerband('DPSG');
        $structure->setIdent('Diözesanverband Köln');
        $structure->setEbeneId(7);
        $structure->setUsedCategories([
            858 => $cat858,
            4 => $cat4,
            882 => $cat882,
            7 => $cat7,
            12 => $cat12,
            3 => $cat3,
            105 => $cat105,
            5 => $cat5,
            11 => $cat11,
            182 => $cat182,
            810 => $cat810,
            43 => $cat43,
            125 => $cat125,
            59 => $cat59,
            10 => $cat10,
            2 => $cat2,
            9 => $cat9,
            881 => $cat881,
            38 => $cat38,
            6 => $cat6
        ]);
        $structure->setForcedCategories([
            'sections/leaders' => [886 => $cat886, 16 => $cat16, 17 => $cat17, 18 => $cat18, 19 => $cat19, 20 => $cat20],
            'DPSG-Ausbildung' => [476 => $cat476, 179 => $cat179, 331 => $cat331, 330 => $cat330, 477 => $cat477, 865 => $cat865, 478 => $cat478, 479 => $cat479, 333 => $cat333, 480 => $cat480, 820 => $cat820, 332 => $cat332, 328 => $cat328, 481 => $cat481, 483 => $cat483, 484 => $cat484, 485 => $cat485, 36 => $cat36, 486 => $cat486, 701 => $cat701, 702 => $cat702, 489 => $cat489, 897 => $cat897],
        ]);

        $kalenderUser = new User();
        $kalenderUser->setUsername('kalender-1.0');
        $kalenderUser->setFirstName('Kalender');
        $kalenderUser->setLastName('1.0');
        $kalenderUser->setSex('m');
        $kalenderUser->setUid('kalender-1.0');


        $event = new Event();
        $event->setUid(792);
        $event->setTitle('Bezirksvorständestreffen');
        $event->setOrganizer('');
        $event->setTargetGroup('');
        $event->setStartDate(\DateTime::createFromFormat('Y-m-d H:i:s', '2001-03-15 00:00:00'));
        $event->setStartTime('19:30:00');
        $event->setEndDate(\DateTime::createFromFormat('Y-m-d H:i:s', '2001-03-15 00:00:00'));
        $event->setEndTime('19:30:00');
        $event->setZip('');
        $event->setLocation('');
        $event->setUrlText('');
        $event->setUrl('');
        $event->setDescription("im Diözesanzentrum Rolandstraße\n\n(Autor: Webteam (mfl))");
        $event->setStufen([]);
        $event->setCategories([1 => $cat]);
        $event->setStructure($structure);
        $event->setChangedBy($kalenderUser);
        $event->setCreatedBy($kalenderUser);
        $event->setCreatedAt(\DateTime::createFromFormat('Y-m-d H:i:s', '2003-07-15 00:40:29'));
        $event->setChangedAt(\DateTime::createFromFormat('Y-m-d H:i:s', '2003-07-15 00:40:29'));

        $ret = $this->sn->get_events_with_ids(4, [792]);
        $this->assertEquals([$event], $ret);
    }

    public function testCreateEvent() {
        $cat = new Categorie();
        $cat->setUid(1);
        $cat->setText('Sonstiges');


        $structure = new Structure();
        $structure->setUid('4');

        $kalenderUser = new User();
        $kalenderUser->setUsername('kalender-1.0');
        $kalenderUser->setUid('kalender-1.0');


        $event = new Event();
        $event->setUid(null);
        $event->setTitle('Bezirksvorständestreffen');
        $event->setOrganizer('');
        $event->setTargetGroup('');
        $event->setStartDate(\DateTime::createFromFormat('Y-m-d H:i:s', '2001-03-15 00:00:00'));
        $event->setStartTime('19:30:00');
        $event->setEndDate(\DateTime::createFromFormat('Y-m-d H:i:s', '2001-03-15 00:00:00'));
        $event->setEndTime('19:30:00');
        $event->setZip('');
        $event->setLocation('');
        $event->setUrlText('');
        $event->setUrl('');
        $event->setDescription("im Diözesanzentrum Rolandstraße\n\n(Autor: Webteam (mfl))");
        $event->setStufen([]);
        $event->setCategories([1 => $cat]);
        $event->setStructure($structure);
        $event->setChangedBy($kalenderUser);
        $event->setCreatedBy($kalenderUser);
        $event->setCreatedAt(\DateTime::createFromFormat('Y-m-d H:i:s', '2003-07-15 00:40:29'));
        $event->setChangedAt(\DateTime::createFromFormat('Y-m-d H:i:s', '2003-07-15 00:40:29'));


        $this->sn->loginUser(self::API_USER, self::API_KEY);
        $res = $this->sn->write_event($event);


        // the answer does not contain the structure, changedby and createdby, since they are not cached
        $event->setUid(23);
        $event->setStructure(null);
        $event->setChangedBy(null);
        $event->setCreatedBy(null);
        $event->setChangedAt(new \DateTime());
        $event->setCreatedAt(new \DateTime());

        $this->assertEquals($event, $res);

    }

    public function testUpdateEvent() {
        $cat = new Categorie();
        $cat->setUid(1);
        $cat->setText('Sonstiges');


        $structure = new Structure();
        $structure->setUid('4');

        $kalenderUser = new User();
        $kalenderUser->setUsername('kalender-1.0');
        $kalenderUser->setUid('kalender-1.0');


        $event = new Event();
        $event->setUid(792);
        $event->setTitle('Bezirksvorständestreffen');
        $event->setOrganizer('');
        $event->setTargetGroup('');
        $event->setStartDate(\DateTime::createFromFormat('Y-m-d H:i:s', '2001-03-15 00:00:00'));
        $event->setStartTime('19:30:00');
        $event->setEndDate(\DateTime::createFromFormat('Y-m-d H:i:s', '2001-03-15 00:00:00'));
        $event->setEndTime('19:30:00');
        $event->setZip('');
        $event->setLocation('');
        $event->setUrlText('');
        $event->setUrl('');
        $event->setDescription("im Diözesanzentrum Rolandstraße\n\n(Autor: Webteam (mfl))");
        $event->setStufen([]);
        $event->setCategories([1 => $cat]);
        $event->setStructure($structure);
        $event->setChangedBy($kalenderUser);
        $event->setCreatedBy($kalenderUser);
        $event->setCreatedAt(\DateTime::createFromFormat('Y-m-d H:i:s', '2003-07-15 00:40:29'));
        $event->setChangedAt(\DateTime::createFromFormat('Y-m-d H:i:s', '2003-07-15 00:40:29'));


        $this->sn->loginUser(self::API_USER, self::API_KEY);
        $res = $this->sn->write_event($event);


        // the answer does not contain the structure, changedby and createdby, since they are not cached
        $event->setStructure(null);
        $event->setChangedBy(null);
        $event->setCreatedBy(null);
        $event->setChangedAt(new \DateTime());

        $this->assertEquals($event, $res);

    }


    public function testDeleteEvent() {
        $this->sn->loginUser(self::API_USER, self::API_KEY);
        $res = $this->sn->delete_event(4, 23);

        $this->assertEquals(['type' => 'ok', 'content' => ['Code' => 0, 'Value' => 'object Deleted']], $res);
    }


}


/**
 * Mocks
 */

DEFINE('CACHE_DIR', dirname(dirname(__FILE__)) . "/Fixtures/");

class JsonRPCClientHelperMock extends JsonRPCClientHelper {
    private $cache_dir = null;

    public function __construct($url, $debug = false, $cache_dir) {
        parent::__construct($url, $debug);
        $this->cache_dir = $cache_dir;
    }

    /**
     * Performs a jsonRCP request and gets the results as an array
     *
     * @param string $method
     * @param array  $params
     *
     * @return array|bool
     * @throws \Exception
     */
    public function __call($method, $params) {
        // check
        if (!is_scalar($method)) {
            throw new Exception('Method name has no scalar value');
        }

        // check
        if (is_array($params)) {
            // no keys
            $params = array_values($params);
        } else {
            throw new Exception('Params must be given as array');
        }

        $cache_file = $this->cache_dir . $method . ".json";
        $cache = [];
        if (is_file($cache_file)) {
            $cache = json_decode(file_get_contents($cache_file), true);
        }

        $param_json = json_encode($params);

        if (!isset($cache[$param_json])) {
            $cache[$param_json] = parent::__call($method, $params);
            file_put_contents($cache_file, json_encode($cache));
        }

        return $cache[$param_json];
    }

    public function setData($type, $id, $data, $api_user, $auth) {
        // we only return the data
        $data['Last_Modified_At'] = time();
        $data['Last_Modified_By'] = $api_user;

        if (!is_numeric($id)) {
            $id = -1;
        }

        if ($id == -1) {
            $data['Created_At'] = time();
            $data['Created_By'] = $api_user;
            $data['UID'] = 23;
            $data['ID'] = 23;
        }

        return $data;
    }

    public function deleteObject($type, $ssid, $id, $api_user, $auth) {
        return ['type' => 'ok', 'content' => ['Code' => 0, 'Value' => 'object Deleted']];
    }
}

/**
 * We mock time() and rand() so we have predictable values.
 */
namespace ScoutNet\Api;

/**
 * mock time
 *
 * @return int
 */
function time() {
    return \ScoutNet\Api\Tests\ApiTest::MOCKED_TIME_VALUE;
}

/**
 * mock random
 *
 * @return int
 */
function rand() {
    return \ScoutNet\Api\Tests\ApiTest::MOCKED_RAND_VALUE;
}

/*

$testEvent = Array(
	'ID' => -1, // id of event to update -1 for new event
	'SSID' => $ssid,
	'Title' => 'F+F Mitgliederversammlung',
	'Organizer' => 'Freundes- und Förderkreis',
	'Target_Group' => 'Freunde',
	'Start' => 1354294800,
	'End' => 1354294800,
	'All_Day' => false,
	'ZIP' => '',
	'Location' => 'Tagungs- und Gästehaus Rolandstr.',
	'URL_Text' => '',
	'URL' => '',
	'Description' => '',
	'Stufen' => Array (),
	'Keywords' => Array (
		'193' => 1,
		'543' => 1,
	)
);

$testEvent = $sn->write_event($testEvent['ID'], $testEvent, $scoutnetUser, $api_key);
echo "event written. It has ID ".$testEvent['ID']."\n";

echo '---------- write delete event -----------------'."\n";
$sn->delete_event($ssid, $testEvent['ID'], $scoutnetUser, $api_key);
echo 'event deleted.'."\n";
*/
