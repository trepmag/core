<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Util
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * SecurityUtil.
 *
 * Notes on security system.
 *
 * Special UID and GIDS:
 *  UID -1 corresponds to 'all users', includes unregistered users
 *  GID -1 corresponds to 'all groups', includes unregistered users
 *  UID 0 corresponds to unregistered users
 *  GID 0 corresponds to unregistered users.
 */
class SecurityUtil
{
    /**
     * Holds the array of security schemas.
     *
     * @var array
     */
    protected static $schemas = array();

    const PERMS_ALL = -1;
    const PERMS_UNREGISTERED = 0;

    // Default salt delimeter
    const SALT_DELIM = '$';

    /**
     * Retrieve the security schemas.
     *
     * @return array The security schemas.
     */
    public static function getSchemas()
    {
        return self::$schemas;
    }

    /**
     * Set the security schemas array.
     *
     * @param array $schemas The security schemas.
     *
     * @return void
     */
    public static function setSchemas($schemas)
    {
        self::$schemas = $schemas;
    }

    /**
     * Generate a security token.
     *
     * @param ContainerBuilder $container   ContainerBuilder (default = null).
     * @param boolean          $forceUnique Force a unique token regardless of system settings.
     *
     * @return string
     */
    public static function generateCsrfToken(ContainerBuilder $container = null, $forceUnique = false)
    {
        if (!$container) {
            $container = ServiceUtil::getManager();
        }

        $tokenGenerator = $container->get('token.generator');
        $session = $container->get('request')->getSession();
        if (!$forceUnique && $session->get('sessioncsrftokenonetime')) {
            $storage = $tokenGenerator->getStorage();
            $tokenId = $session->get('sessioncsrftokenid');
            $data = $storage->get($tokenId);
            if (!$data) {
                $tokenGenerator->generate($tokenGenerator->uniqueId(), time());
                $tokenGenerator->save();
                $session->set('sessioncsrftokenid', $tokenGenerator->getId());
                return $tokenGenerator->getToken();
            }
            return $data['token'];
        }

        $tokenGenerator->generate($tokenGenerator->uniqueId(), time());
        $tokenGenerator->save();

        return $tokenGenerator->getToken();
    }

    /**
     * Validate a given security token.
     *
     * @param string           $token     Token to be validated.
     * @param ContainerBuilder $container ContainerBuilder default = null.
     *
     * @return boolean
     */
    public static function validateCsrfToken($token, ContainerBuilder $container = null)
    {
        if (!$container) {
            $container = ServiceUtil::getManager();
        }

        $tokenValidator = $container->get('token.validator');
        $session = $container->get('request')->getSession();
        if ($session->get('sessioncsrftokenonetime')) {
            $result = $tokenValidator->validate($token, false, false);
            if ($result) {
                return true;
            }

            $session->invalidate(); // something went wrong so expire the session.
        }

        return $tokenValidator->validate($token);
    }

    /**
     * Check permissions
     *
     * @param string   $component Component.
     * @param string   $instance  Instance.
     * @param constant $level     Level.
     * @param integer  $user      User Id.
     *
     * @return boolean
     */
    public static function checkPermission($component = null, $instance = null, $level = null, $user = null)
    {
        static $groupperms = array();

        if (!is_numeric($level)) {
            return z_exit(__f('Invalid security level [%1$s] received in %2$s', array($level, 'SecurityUtil::checkPermission')));
        }

        if (!$user) {
            $user = UserUtil::getVar('uid');
        }

        if (!isset($GLOBALS['authinfogathered'][$user]) || (int)$GLOBALS['authinfogathered'][$user] == 0) {
            $groupperms[$user] = self::getAuthInfo($user); // First time here - get auth info
            if (count($groupperms[$user]) == 0) {
                return false; // No permissions
            }
        }

        $res = self::getSecurityLevel($groupperms[$user], $component, $instance) >= $level;

        return $res;
    }

    /**
     * Register a permission schema.
     *
     * @param string $component Component.
     * @param string $schema    Schema.
     *
     * @return boolean
     */
    public static function registerPermissionSchema($component, $schema)
    {
        if (!empty(self::$schemas[$component])) {
            return false;
        }

        self::$schemas[$component] = $schema;
        return true;
    }

    /**
     * Get auth info.
     *
     * @param integer $user User Id.
     *
     * @return array Two element array of user and group permissions.
     */
    public static function getAuthInfo($user = null)
    {
        $groupperms = array();

        $uids[] = -1;

        // get user ID
        if (!isset($user)) {
            if (!UserUtil::isLoggedIn()) {
                // Unregistered UID
                $uids[] = 0;
                $vars['Active User'] = 'unregistered';
            } else {
                $uids[] = UserUtil::getVar('uid');
                $vars['Active User'] = UserUtil::getVar('uid');
            }
        } else {
            $uids[] = $user;
            $vars['Active User'] = $user;
        }

        $em = ServiceUtil::get('doctrine')->getEntityManager();

        // get all groups that user is in
        $groupmembership = $em->getRepository('GroupsModule\Entity\GroupMembership')->findBy(array('uid' => $uids));

        if ($groupmembership === false) {
            return $groupperms;
        }

        $fldArray = array();
        foreach ($groupmembership as $gm) {
            $fldArray[] = $gm['gid'];
        }

        static $usergroups = array();
        if (!$usergroups) {
            $usergroups[] = -1;
            if (!UserUtil::isLoggedIn()) {
                $usergroups[] = 0; // Unregistered GID
            }
        }

        $allgroups = array_merge($usergroups, $fldArray);

        // get all permissions for the groups that the user belongs to
        $permissions = $em->getRepository('PermissionsModule\Entity\Permission')->findBy(array('gid' => $allgroups), array('sequence' => 'ASC'));

        if (!$permissions) {
            return $groupperms;
        }

        foreach ($permissions as $permission) {
            $component = self::_fixsecuritystring($permission['component']);
            $instance = self::_fixsecuritystring($permission['instance']);
            $level = self::_fixsecuritystring($permission['level']);

            // Search/replace of special names
            preg_match_all('/<([^>]+)>/', $instance, $res);
            $size = count($res[1]);
            for ($i = 0; $i < $size; $i++) {
                $instance = preg_replace('/<([^>]+)>/', $vars[$res[1][$i]], $instance, 1);
            }

            $groupperms[] = array(
                'component' => $component,
                'instance' => $instance,
                'level' => $level
            );
        }

        // we've now got the permissions info
        $GLOBALS['authinfogathered'][$user] = 1;

        return $groupperms;
    }

    /**
     * Get security Level
     *
     * @param array  $perms     Array of permissions.
     * @param string $component Component.
     * @param string $instance  Instance.
     *
     * @return integer Matching security level.
     */
    public static function getSecurityLevel($perms, $component, $instance)
    {
        $level = ACCESS_INVALID;

        // If we get a test component or instance purely consisting of ':' signs
        // then it counts as blank
        //itevo
        if ($component == str_repeat(':', strlen($component))) {
            $component = '';
        }
        if ($instance == str_repeat(':', strlen($instance))) {
            $instance = '';
        }

        // Test for generic permission
        if ((empty($component)) && (empty($instance))) {
            // Looking for best permission
            foreach ($perms as $perm) {
                if ($perm['level'] > $level) {
                    $level = $perm['level'];
                }
            }
            return $level;
        }

        // Test if user has ANY access to given component, without determining exact instance
        if ($instance == 'ANY') {
            $levels = array($level);
            foreach ($perms as $perm) {
                // component check
                if (!preg_match("=^$perm[component]$=", $component)) {
                    continue; // component doestn't match.
                }

                // if component matches -  keep the level we found
                $levels[] = $perm['level'];

                // check that the instance matches :: or '' (nothing)
                if ((preg_match("=^$perm[instance]$=", '::') || preg_match("=^$perm[instance]$=", ''))) {
                    break; // instance matches - stop searching
                }
            }

            // select the highest level among found
            $level = max($levels);
            return $level;
        }

        // Test for generic instance
        // additional fixes by BMW [larsneo]
        // if the instance is empty, then we're looking for the per-module
        // permissions.
        if (empty($instance)) {
            // if $instance is empty, then there must be a component.
            // Looking for best permission
            foreach ($perms as $perm) {
                // component check
                if (!preg_match("=^$perm[component]$=", $component)) {
                    continue; // component doestn't match.
                }

                // check that the instance matches :: or '' (nothing)
                if (!(preg_match("=^$perm[instance]$=", '::') || preg_match("=^$perm[instance]$=", ''))) {
                    continue; // instance does not match
                }

                // We have a match - set the level and quit
                $level = $perm['level'];
                break;
            }
            return $level;
        }

        // Normal permissions check
        // there *is* a $instance at this point.
        foreach ($perms as $perm) {

            // if there is a component, check that it matches
            if (($component != '') && (!preg_match("=^$perm[component]$=", $component))) {
                // component exists, and doestn't match.
                continue;
            }

            // Confirm that instance matches
            if (!preg_match("=^$perm[instance]$=", $instance)) {
                // instance does not match
                continue;
            }

            // We have a match - set the level and quit looking
            $level = $perm['level'];
            break;
        }

        return $level;
    }

    /**
     * Fix security string.
     *
     * @param string $string String.
     *
     * @return string
     */
    public static function _fixsecuritystring($string)
    {
        if (empty($string)) {
            $string = '.*';
        }
        if (strpos($string, ':') === 0) {
            $string = '.*' . $string;
        }
        $string = str_replace('::', ':.*:', $string);
        if (strrpos($string, ':') === strlen($string) - 1) {
            $string = $string . '.*';
        }
        return $string;
    }

    /**
     * Sign data object leaving data clearly visible.
     *
     * @param array $data Data object.
     *
     * @return string Serialized string of signed data.
     */
    public static function signData($data)
    {
        $key = System::getVar('signingkey');
        $unsignedData = serialize($data);
        $signature = sha1($unsignedData . $key);
        $signedData = serialize(array($unsignedData, $signature));

        return $signedData;
    }

    /**
     * Verify signed data object.
     *
     * @param string $data String of serialized $data.
     *
     * @return mixed Array or string of data if true or bool false if false.
     */
    public static function checkSignedData($data)
    {
        $key = System::getVar('signingkey');
        $signedData = unserialize($data);
        $signature = sha1($signedData[0] . $key);
        if ($signature != $signedData[1]) {
            return false;
        }

        return unserialize($signedData[0]);
    }

    /**
     * Hashes the data with the specified salt value and returns a string containing the hash method, salt and hash.
     *
     * @param string $unhashedData         The data to be salted and hashed.
     * @param string $hashMethodName       Any value returned by hash_algo().
     * @param string $saltStr              Any valid string, including the empty string, with which to salt the unhashed data before hashing.
     * @param array  $hashMethodNameToCode An array indexed by algorithm names (from hash_algos()) used to encode the hashing algorithm
     *                                         name and include it on the salted hash string; optional, if not specified, then the
     *                                         algorithm name is included in the string returned (which could be considered less than secure!).
     * @param string $saltDelimeter        The delimiter between the salt and the hash, must be a single character.
     *
     * @return string|bool The algorithm name (or code if $hashMethodNameToCode specified), salt and hashed data separated by the salt delimiter;
     *                      false if an error occured.
     */
    public static function buildSaltedHash($unhashedData, $hashMethodName, $saltStr, array $hashMethodNameToCode = array(), $saltDelimeter = self::SALT_DELIM)
    {
        $saltedHash = false;
        $algoList = hash_algos();

        if ((array_search($hashMethodName, $algoList) !== false) && is_string($saltStr) && is_string($saltDelimeter) && (strlen($saltDelimeter) == 1)) {
            $hashedData = hash($hashMethodName, $saltStr . $unhashedData);
            if (!empty($hashMethodNameToCode)) {
                if (isset($hashMethodNameToCode[$hashMethodName])) {
                    $saltedHash = $hashMethodNameToCode[$hashMethodName] . $saltDelimeter . $saltStr . $saltDelimeter . $hashedData;
                } else {
                    $saltedHash = false;
                }
            } else {
                $saltedHash = $hashMethodName . $saltDelimeter . $saltStr . $saltDelimeter . $hashedData;
            }
        }

        return $saltedHash;
    }

    /**
     * Hashes the data with a random salt value and returns a string containing the hash method, salt and hash.
     *
     * @param string $unhashedData         The data to be salted and hashed.
     * @param string $hashMethodName       Any value returned by hash_algo().
     * @param array  $hashMethodNameToCode An array indexed by algorithm names (from hash_algos()) used to encode the hashing algorithm
     *                                         name and include it on the salted hash string; optional, if not specified, then the
     *                                         algorithm name is included in the string returned (which could be considered less than secure!).
     * @param int    $saltLength           The number of random characters to use in the salt.
     * @param string $saltDelimeter        The delimiter between the salt and the hash, must be a single character.
     *
     * @return string|bool The algorithm name (or code if $hashMethodNameToCode specified), salt and hashed data separated by the salt delimiter;
     *                      false if an error occured.
     */
    public static function getSaltedHash($unhashedData, $hashMethodName, array $hashMethodNameToCode = array(), $saltLength = 5, $saltDelimeter = self::SALT_DELIM)
    {
        $saltedHash = false;
        $saltStr = RandomUtil::getString($saltLength, $saltLength, false, true, true, true, true, true, false, array($saltDelimeter));
        return self::buildSaltedHash($unhashedData, $hashMethodName, $saltStr, $hashMethodNameToCode, $saltDelimeter);
    }

    /**
     * Checks the given data against the given salted hash to see if they match.
     *
     * @param string $unhashedData         The data to be salted and hashed.
     * @param string $saltedHash           The salted hash.
     * @param array  $hashMethodCodeToName An array indexed by algorithm names (from hash_algos()) used to encode the hashing algorithm
     *                                         name and include it on the salted hash string; optional, if not specified, then the
     *                                         algorithm name is included in the string returned (which could be considered less than secure!).
     * @param string $saltDelimeter        The delimiter between the salt and the hash, must be a single character.
     *
     * @return integer|bool If the data matches the salted hash, then 1; If the data does not match, then 0; false if an error occured (Note:
     *                      both 0 and false evaluate to false in boolean expressions--use strict comparisons to differentiate).
     */
    public static function checkSaltedHash($unhashedData, $saltedHash, array $hashMethodCodeToName = array(), $saltDelimeter = self::SALT_DELIM)
    {
        $dataMatches = false;

        $algoList = hash_algos();

        if (is_string($unhashedData) && is_string($saltedHash) && is_string($saltDelimeter) && (strlen($saltDelimeter) == 1)
                && (strpos($saltedHash, $saltDelimeter) !== false)) {
            list ($hashMethod, $saltStr, $correctHash) = explode($saltDelimeter, $saltedHash);

            if (!empty($hashMethodCodeToName)) {
                if (is_numeric($hashMethod) && ((int)$hashMethod == $hashMethod)) {
                    $hashMethod = (int)$hashMethod;
                }
                if (isset($hashMethodCodeToName[$hashMethod])) {
                    $hashMethodName = $hashMethodCodeToName[$hashMethod];
                } else {
                    $hashMethodName = $hashMethod;
                }
            } else {
                $hashMethodName = $hashMethod;
            }

            if (array_search($hashMethodName, $algoList) !== false) {
                $dataHash = hash($hashMethodName, $saltStr . $unhashedData);
                $dataMatches = is_string($dataHash) ? (int)($dataHash == $correctHash) : false;
            }
        }

        return $dataMatches;
    }

    /**
     * Translation functions - avoids globals in external code
     *
     * Translate level -> name
     *
     * @param constant $level Access level.
     *
     * @return string Translated access level name.
     */
    public static function accesslevelname($level)
    {
        $accessnames = self::accesslevelnames();
        return $accessnames[$level];
    }

    /**
     * get access level names
     *
     * @return array of access names
     */
    public static function accesslevelnames()
    {
        static $accessnames = null;
        if (!is_array($accessnames)) {
            $accessnames = array(
                    0 => __('No access'),
                    100 => __('Overview access'),
                    200 => __('Read access'),
                    300 => __('Comment access'),
                    400 => __('Moderate access'),
                    500 => __('Edit access'),
                    600 => __('Add access'),
                    700 => __('Delete access'),
                    800 => __('Admin access'));
        }

        return $accessnames;
    }

}
