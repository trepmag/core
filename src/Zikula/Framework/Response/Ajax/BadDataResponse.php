<?php
/**
 * Copyright Zikula Foundation 2010 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Zikula
 * @subpackage Response
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace Zikula\Framework\Response\Ajax;

/**
 * Ajax class.
 */
class BadDataResponse extends AbstractBaseResponse
{
    /**
     * Response code.
     *
     * @var integer
     */
    protected $statusCode = 400;
}
