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
class SolidrockSyncJobsJob extends BaseJob
{
    // Public Properties
    // =========================================================================

    // Private Properties
    // =========================================================================
    private $_jobsToUpdate = [];
    private $_localJobData;

    // Public Methods
    // =========================================================================

    // Public Methods
    // =========================================================================

    public function execute($queue)
    {
        $totalSteps = $this->getTotalSteps();
        $step = 0;

        foreach ($this->_jobsToUpdate as $jobId => $entryId)
        {
            $this->setProgress($queue, $step / $totalSteps);
            
            Craft::Info('Update Jobs: Running Step ' . $step, __METHOD__);

            // Update existing DB entry
            SolidrockSync::$plugin->jobs->updateEntry($entryId, $jobId);

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
        return 'Update local Solidrock job data';
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
        Craft::Info('Update Jobs: Get Total Steps', __METHOD__);

        // Pass false to get all jobs
        // Limited to most recent 1500
        $this->_localJobData = SolidrockSync::$plugin->solidrockJobSyncService->getLocalData(1500);

        if (! $this->_localJobData) {
            Craft::Info('Update Jobs: No local data to work with', __METHOD__);
        }

        $this->_jobsToUpdate[] = $this->_localJobData['jobs'];

        // foreach ($this->_localJobData['jobs'] as $groupId => $entryId) {
        //     $this->_jobsToUpdate[] = $entryId;
        // }

        Craft::Info('Update Jobs - Total Steps: ' . count($this->_jobsToUpdate), __METHOD__);

        return count($this->_jobsToUpdate);
    }
}
