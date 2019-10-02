<?php
/**
 * SolidrockSync plugin for Craft CMS 3.x
 *
 * Communicate and process data from the Solidrock API
 *
 * @link      https://boxhead.io
 * @copyright Copyright (c) 2018 Boxhead
 */

namespace boxhead\solidrocksync\controllers;

use boxhead\solidrocksync\jobs\SolidrockSyncCreateJobJob;
use boxhead\solidrocksync\jobs\SolidrockSyncUpdateJobJob;
use boxhead\solidrocksync\SolidrockSync;
use Craft;
use craft\elements\Entry;
use craft\web\Controller;

/**
 * Default Controller
 *
 * Generally speaking, controllers are the middlemen between the front end of
 * the CP/website and your plugin’s services. They contain action methods which
 * handle individual tasks.
 *
 * A common pattern used throughout Craft involves a controller action gathering
 * post data, saving it on a model, passing the model off to a service, and then
 * responding to the request appropriately depending on the service method’s response.
 *
 * Action methods begin with the prefix “action”, followed by a description of what
 * the method does (for example, actionSaveIngredient()).
 *
 * https://craftcms.com/docs/plugins/controllers
 *
 * @author    Boxhead
 * @package   Solidrocksync
 * @since     1.0.0
 */
class JobsController extends Controller
{

    // Protected Properties
    // =========================================================================

    /**
     * @var    bool|array Allows anonymous access to this controller's actions.
     *         The actions must be in 'kebab-case'
     * @access protected
     */
    protected $allowAnonymous = ['sync-with-remote'];

    private $remoteData;
    private $localData;
    private $settings;

    // Public Methods
    // =========================================================================

    /**
     * Handle a request going to our plugin's SyncWithRemote action URL,
     * e.g.: actions/solidrock-sync/jobs/sync-with-remote
     *
     * @return mixed
     */
    public function actionSyncWithRemote()
    {
        $this->settings = SolidrockSync::$plugin->getSettings();

        $this->remoteData = SolidrockSync::$plugin->jobs->getAPIData();

        if (!$this->remoteData) {
            Craft::Info('Sync Jobs: No api data to work with', __METHOD__);
        }

        $this->localData = SolidrockSync::$plugin->jobs->getLocalData();

        // Determine jobs we need to create vs. update vs. disable

        // Which remote jobs do we have that aren't yet Craft entries?
        $missingIds = array_diff($this->remoteData['ids'], $this->localData['ids']);

        // Which Craft entries do we have that aren't in the remote data?
        $removedIds = array_diff($this->localData['ids'], $this->remoteData['ids']);

        // Which remote jobs to we have that already have a Craft entry?
        $updatingIds = array_diff($this->remoteData['ids'], $missingIds);

        // Provide higher time to retry than the default 300 seconds
        Craft::$app->queue->ttr(600);

        // Create all missing jobs
        foreach ($missingIds as $jobId) {
            Craft::$app->queue->push(new SolidrockSyncCreateJobJob([
                'job' => $this->remoteData['jobs'][$jobId],
            ]));
        }

        // Update all existing jobs
        foreach ($updatingIds as $jobId) {
            $entryId = $this->localData['jobs'][$jobId];
            $job = $this->remoteData['jobs'][$jobId]->job;

            // Find existing Craft Entry Model
            $entry = Entry::find()
                ->sectionId($this->settings->jobsSectionId)
                ->id($entryId)
                ->status(null)
                ->one();

            // If we found a Craft entry update it
            if ($entry) {
                Craft::$app->queue->push(new SolidrockSyncUpdateJobJob([
                    'job' => $this->remoteData['jobs'][$jobId],
                    'entry' => $entry,
                ]));
            }
        }

        foreach ($removedIds as $id) {
            $entryId = $this->localData['jobs'][$id];

            // Create a new instance of the Craft Entry Model
            $entry = Entry::find()
                ->sectionId($this->settings->jobsSectionId)
                ->id($entryId)
                ->status(null)
                ->one();

            // If we've got an entry and it's set to enabled, then disable it
            if ($entry && $entry->enabled) {
                $entry->enabled = false;

                // Re-save the entry
                Craft::$app->elements->saveElement($entry);
            }
        }

        $result = 'Syncing remote Solidrock jobs data';

        return $result;
    }

    private function dd($data)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        die();
    }
}
