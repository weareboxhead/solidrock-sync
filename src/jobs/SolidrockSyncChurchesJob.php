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
class SolidrockSyncChurchesJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    // Private Properties
    // =========================================================================
    private $_churchesToUpdate = [];
    private $_localChurchData;


    // Public Methods
    // =========================================================================

    public function execute($queue)
    {
        $totalSteps = $this->getTotalSteps();
        $step = 0;

        foreach ($this->_churchesToUpdate as $gatheringId => $entryId)
        {
            $this->setProgress($queue, $step / $totalSteps);
            
            Craft::Info('Update Gatherings: Running Step ' . $step, __METHOD__);

            // Update existing DB entry
            SolidrockSync::$plugin->churches->updateEntry($entryId, $gatheringId);

            $step++;
        }
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
        return 'Update local Solidrock Gathering data';
    }


    // Private Methods
    // =========================================================================

    /**
     * Returns the total number of steps for this task.
     *
     * @return int The total number of steps for this task
     */
    private function getTotalSteps(): int
    {
        Craft::Info('Update Gatherings: Get Total Steps', __METHOD__);

        // Pass false to get all churches
        // Limited to most recent 1500
        $this->_localChurchData = SolidrockSync::$plugin->churches->getLocalData(1500);

        if (! $this->_localChurchData) {
            Craft::Info('Update Gatherings: No local data to work with', __METHOD__);
        }

        $this->_churchesToUpdate = $this->_localChurchData['gatherings'];

        // foreach ($this->_localChurchData['gatherings'] as $gatheringId => $entryId) {
        //     $this->_churchesToUpdate[] = $entryId;
        // }

        Craft::Info('Update Gatherings - Total Steps: ' . count($this->_churchesToUpdate), __METHOD__);

        return count($this->_churchesToUpdate);
    }
}
