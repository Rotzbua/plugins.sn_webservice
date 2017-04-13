<?php
namespace ScoutNet\Api\Models;

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

class Permission {
    const AUTH_NO_RIGHT = 1;
    const AUTH_WRITE_ALLOWED = 0;
    const AUTH_REQUESTED = 0;
    const AUTH_REQUEST_PENDING = 2;

    var $state = self::AUTH_NO_RIGHT;
    var $text = '';
    var $type = '';

    function __construct($array = []) {
        $this->state = isset($array['code'])?$array['code']:self::AUTH_NO_RIGHT;
        $this->text = isset($array['text'])?$array['text']:'';
        $this->type = isset($array['type'])?$array['type']:'';
    }

    public function getState() {
        return $this->state;
    }

    public function hasWritePermissionsPending() {
        return $this->state == SELF::AUTH_PENDING;
    }

    public function hasWritePermissions() {
        return $this->state == SELF::AUTH_WRITE_ALLOWED;
    }
}