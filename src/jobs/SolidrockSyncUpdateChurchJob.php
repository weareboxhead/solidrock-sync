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
class SolidrockSyncUpdateChurchJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    public $gathering;
    public $entry;

    // Public Methods
    // =========================================================================

    public function execute($queue)
    {
        SolidrockSync::$plugin->churches->updateEntry($this->entry, $this->gathering);
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
        return 'Updating Solidrock Gathering: ' . $this->gathering->gathering->id;
    }
}
