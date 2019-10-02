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

use boxhead\solidrocksync\jobs\SolidrockSyncCreateChurchJob;
use boxhead\solidrocksync\jobs\SolidrockSyncUpdateChurchJob;
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
class ChurchesController extends Controller
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

    /**
     * Handle a request going to our plugin's SyncWithRemote action URL,
     * e.g.: actions/solidrock-sync/churches/sync-with-remote
     *
     * @return mixed
     */
    public function actionSyncWithRemote()
    {
        $this->settings = SolidrockSync::$plugin->getSettings();

        $this->remoteData = SolidrockSync::$plugin->churches->getAPIData();

        if (!$this->remoteData) {
            Craft::Info('Sync Gatherings: No api data to work with', __METHOD__);
        }

        $this->localData = SolidrockSync::$plugin->churches->getLocalData();

        // Determine churches we need to create vs. update vs. disable

        // Which remote gatherings do we have that aren't yet Craft entries?
        $missingIds = array_diff($this->remoteData['ids'], $this->localData['ids']);

        // Which Craft entries do we have that aren't in the remote data?
        $removedIds = array_diff($this->localData['ids'], $this->remoteData['ids']);

        // Which remote gatherings to we have that already have a Craft entry?
        $updatingIds = array_diff($this->remoteData['ids'], $missingIds);

        // Provide higher time to retry than the default 300 seconds
        Craft::$app->queue->ttr(600);

        // Create all missing gatherings
        foreach ($missingIds as $gatheringId) {
            Craft::$app->queue->push(new SolidrockSyncCreateChurchJob([
                'gathering' => $this->remoteData['gatherings'][$gatheringId],
            ]));
        }

        // Update all existing gatherings
        foreach ($updatingIds as $gatheringId) {
            $entryId = $this->localData['gatherings'][$gatheringId];
            $gathering = $this->remoteData['gatherings'][$gatheringId]->gathering;

            // Find existing Craft Entry Model
            $entry = Entry::find()
                ->sectionId($this->settings->churchesSectionId)
                ->id($entryId)
                ->status(null)
                ->one();

            // Check if the SR record has been updated since we last updated the craft record
            // and only update the craft entry if it has
            $srLastUpdated = strtotime($gathering->last_updated);

            if (
                $entry &&
                ($srLastUpdated > $entry->dateUpdated->getTimestamp())
            ) {
                Craft::$app->queue->push(new SolidrockSyncUpdateChurchJob([
                    'gathering' => $this->remoteData['gatherings'][$gatheringId],
                    'entry' => $entry,
                ]));
            }
        }

        foreach ($removedIds as $id) {
            $entryId = $this->localData['gatherings'][$id];

            // Create a new instance of the Craft Entry Model
            $entry = Entry::find()
                ->sectionId($this->settings->churchesSectionId)
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

        return 'Syncing remote Solidrock gathering data';
    }

    private function dd($data)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        die();
    }
}
