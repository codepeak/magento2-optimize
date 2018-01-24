<?php
/**
 * A Magento 2 module named Codepeak_Optimize
 * Copyright (C) 2018 Codepeak AB 2018
 *
 * This file is part of Codepeak_Optimize.
 *
 * Codepeak_Optimize is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Codepeak\Optimize\Cron;

use Codepeak\Core\Logger\Logger;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Class Sessions
 *
 * @package  Codepeak\Optimize\Cron
 * @license  GNU License http://www.gnu.org/licenses/
 * @author   Robert Lord, Codepeak AB <robert@codepeak.se>
 * @link     https://codepeak.se
 */
class Sessions
{
    /**
     * @var
     */
    const DELETE_LIMIT = 1000;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * Sessions constructor.
     *
     * @param Logger               $logger
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection   $resourceConnection
     */
    public function __construct(
        Logger $logger,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection
    ) {
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Execute the cron
     *
     * @throws \Zend_Db_Statement_Exception
     */
    public function execute()
    {
        // Make sure function is enabled
        if ($this->scopeConfig->getValue('codepeak_optimize/session/enabled') == '1') {
            // Fetch the expiry limit
            $expiryLimit = intval($this->scopeConfig->getValue('codepeak_optimize/session/expiry_limit'));

            // Fetch the delete limit
            $deleteLimit = intval($this->scopeConfig->getValue('codepeak_optimize/session/delete_limit'));

            // Set default value if nothing was given
            if (!$deleteLimit) {
                $deleteLimit = 1000;
            }

            // Make a note in the log about this
            $this->logger->info('Looking for expired sessions with an additional ' . $expiryLimit . ' days...');

            // Calculate the expiry limit in unix timestamp
            $expiryLimit = time() - ($expiryLimit * 86400);

            // Fetch the table name
            $sessionTableName = $this->resourceConnection->getTableName('session');

            // Setup the count SQL
            $sqlCount = 'SELECT COUNT(*) as `count` FROM `%s` WHERE `session_expires` <= %s LIMIT ' . $deleteLimit;
            $sqlCount = sprintf(
                $sqlCount,
                $sessionTableName,
                $expiryLimit
            );

            // Setup the removal SQL
            $sqlRemove = 'DELETE FROM `%s` WHERE `session_expires` <= %s LIMIT ' . $deleteLimit;
            $sqlRemove = sprintf(
                $sqlRemove,
                $sessionTableName,
                $expiryLimit
            );

            // Fetch a database connection
            $connection = $this->resourceConnection->getConnection();

            // Count the number of items to be removed
            $removalCount = intval($connection->query($sqlCount)->fetchColumn(0));

            // Remove the sessions
            $connection->query($sqlRemove);

            // Make a note in the log about this
            $this->logger->info('Finished cleaning up. Removed ' . $removalCount . ' expired sessions');
        }
    }
}
