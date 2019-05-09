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
 * use boxhead\solidrocksync\tasks\ChurchesTask as ChurchesTaskTask;
 *
 * $tasks = Craft::$app->getTasks();
 * if (!$tasks->areTasksPending(ChurchesTaskTask::class)) {
 *     $tasks->createTask(ChurchesTaskTask::class);
 * }
 *
 * https://craftcms.com/classreference/services/TasksService
 *
 * @author    Boxhead
 * @package   SolidrockSync
 * @since     1.0.0
 */
class ChurchesTask extends Task
{
    // Public Properties
    // =========================================================================

    // Private Properties
    // =========================================================================
    private $_churchesToUpdate = [];
    private $_localChurchData;

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
        Craft::Info('Update Gatherings: Get Total Steps', __METHOD__);

        // Pass false to get all churches
        // Limited to most recent 1500
        $this->_localChurchData = SolidrockSync::$plugin->churches->getLocalData(1500);

        if (! $this->_localChurchData) {
            Craft::Info('Update Gatherings: No local data to work with', __METHOD__);
        }

        foreach ($this->_localChurchData['churches'] as $groupId => $entryId) {
            $this->_churchesToUpdate[] = $entryId;
        }

        Craft::Info('Update Gatherings - Total Steps: ' . count($this->_churchesToUpdate), __METHOD__);

        return count($this->_churchesToUpdate);
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
        Craft::Info('Update Gatherings: Running Step ' . $step, __METHOD__);

        $id = $this->_churchesToUpdate[$step];

        // Update existing DB entry
        SolidrockSync::$plugin->churches->updateEntry($id);

        return true;
    }


    /**
     * Returns the default description for this task.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Update local Solidrock Gathering data';
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
        return Craft::t('solidrock-sync', 'SolidrockSyncChurchesTask');
    }
}
