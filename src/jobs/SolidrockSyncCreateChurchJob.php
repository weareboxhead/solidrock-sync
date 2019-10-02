<?php
/**
 * SolidrockSync plugin for Craft CMS 3.x
 *
 * Communicate and process data from the Solidrock API
 *
 * @link      https://boxhead.io
 * @copyright Copyright (c) 2018 Boxhead
 */

namespace boxhead\solidrocksync\jobs;

use boxhead\solidrocksync\SolidrockSync;
use Craft;
use craft\queue\BaseJob;

/**
 * @author    Boxhead
 * @package   SolidrockSync
 * @since     1.0.0
 */
class SolidrockSyncCreateChurchJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    public $gathering;

    // Public Methods
    // =========================================================================

    public function execute($queue)
    {
        SolidrockSync::$plugin->churches->createEntry($this->gathering);
    }

    // Protected Methods
    // =========================================================================

    /**
     * Returns a default description for [[getDescription()]], if [[description]] isn’t set.
     *
     * @return string The default task description
     */
    protected function defaultDescription(): string
    {
        return 'Creating Solidrock Gathering: ' . $this->gathering->gathering->id;
    }
}
