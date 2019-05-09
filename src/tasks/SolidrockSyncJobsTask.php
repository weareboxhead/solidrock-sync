<?php
/**
 * SolidrockSync plugin for Craft CMS 3.x
 *
 * Communicate and process data from the Solidrock API
 *
 * @link      https://boxhead.io
 * @copyright Copyright (c) 2018 Boxhead
 */

namespace boxhead\solidrocksync\tasks;

use boxhead\solidrocksync\SolidrockSync;

use Craft;
use craft\base\Task;

/**
 * SolidrockSyncTask Task
 *
 * Tasks let you run background processing for things that take a long time,
 * dividing them up into steps.  For example, Asset Transforms are regenerated
 * using Tasks.
 *
 * Keep in mind that tasks only get timeslices to run when Craft is handling
 * requests on your website.  If you need a task to be run on a regular basis,
 * write a Controller that triggers it, and set up a cron job to
 * trigger the controller.
 *
 * The pattern used to queue up a task for running is:
 *
 * use boxhead\solidrocksync\tasks\SolidrockSyncJobsTask as SolidrockSyncJobsTaskTask;
 *
 * $tasks = Craft::$app->getTasks();
 * if (!$tasks->areTasksPending(SolidrockSyncJobsTaskTask::class)) {
 *     $tasks->createTask(SolidrockSyncJobsTaskTask::class);
 * }
 *
 * https://craftcms.com/classreference/services/TasksService
 *
 * @author    Boxhead
 * @package   SolidrockSync
 * @since     1.0.0
 */
class SolidrockSyncJobsTask extends Task
{
    // Public Properties
    // =========================================================================

    // Private Properties
    // =========================================================================
    private $_jobsToUpdate = [];
    private $_localJobData;

    // Public Methods
    // =========================================================================

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    /**
     * Returns the total number of steps for this task.
     *
     * @return int The total number of steps for this task
     */
    public function getTotalSteps(): int
    {
        Craft::Info('Update Jobs: Get Total Steps', __METHOD__);

        // Pass false to get all jobs
        // Limited to most recent 1500
        $this->_localJobData = SolidrockSync::$plugin->solidrockJobSyncService->getLocalData(1500);

        if (! $this->_localJobData) {
            Craft::Info('Update Jobs: No local data to work with', __METHOD__);
        }

        foreach ($this->_localJobData['jobs'] as $groupId => $entryId) {
            $this->_jobsToUpdate[] = $entryId;
        }

        Craft::Info('Update Jobs - Total Steps: ' . count($this->_jobsToUpdate), __METHOD__);

        return count($this->_jobsToUpdate);
    }

    /**
     * Runs a task step.
     *
     * @param int $step The step to run
     *
     * @return bool|string True if the step was successful, false or an error message if not
     */
    public function runStep(int $step)
    {
        Craft::Info('Update Jobs: Running Step ' . $step, __METHOD__);

        $id = $this->_jobsToUpdate[$step];

        // Update existing DB entry
        SolidrockSync::$plugin->solidrockJobSyncService->updateEntry($id);

        return true;
    }


    /**
     * Returns the default description for this task.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Update local Solidrock job data';
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
        return Craft::t('solidrock-sync', 'SolidrockSyncJobsTask');
    }
}
