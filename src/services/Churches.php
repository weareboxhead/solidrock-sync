<?php
/**
 * SolidrockSync plugin for Craft CMS 3.x
 *
 * Communicate and process data from the SolidrockSync API
 *
 * @link      https://boxhead.io
 * @copyright Copyright (c) 2018 Boxhead
 */

namespace boxhead\solidrocksync\services;

use boxhead\solidrocksync\SolidrockSync;
use Craft;
use craft\base\Component;
use craft\elements\Entry;
use DateTime;
use GuzzleHttp\Client;

/**
 * Churches Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Boxhead
 * @package   SolidrockSync
 * @since     1.0.0
 */
class Churches extends Component
{
    private $settings;
    private $remoteData;
    private $localData;
    private $client;

    // Public Methods
    // =========================================================================
    public function getAPIData()
    {
        if (!$this->settings) {
            $this->settings = SolidrockSync::$plugin->getSettings();
        }

        // Create Guzzle Client
        if (!$this->client) {
            $this->createGuzzleClient();
        }

        Craft::info('SolidrockSync: Begin sync with API', __METHOD__);

        // Get all Solidrock Gatherings
        $response = $this->client->request('POST', 'churches/all_live_gatherings.json', [
            'form_params' => [
                'apiKey' => $this->settings->apiKey,
            ],
        ]);

        // Do we have a success response?
        if ($response->getStatusCode() !== 200) {
            Craft::error('SolidrockSync: API Reponse Error ' . $response->getStatusCode() . ": " . $response->getReasonPhrase(), __METHOD__);

            return false;
        }

        $body = json_decode($response->getBody());

        // Are there any results
        if (count(get_object_vars($body->gatherings)) === 0) {
            Craft::error('SolidrockSync: No results from API Request', __METHOD__);

            return false;
        }

        $data = array(
            'ids' => [],
            'gatherings' => []
        );

        // For each gathering
        foreach ($body->gatherings as $gatheringId => $gathering) {
            // Add this id to our array
            $data['ids'][] = $gatheringId;

            // Add this gathering to our array, using the id as the key
            $data['gatherings'][$gatheringId] = $gathering;
        }

        Craft::info('SolidrockSync: Finished getting remote data', __METHOD__);

        return $data;
    }

    public function getLocalData()
    {
        Craft::info('SolidrockSync: Get local Gathering data', __METHOD__);

        if (!\is_array($this->settings)) {
            $this->settings = SolidrockSync::$plugin->getSettings();
        }

        // Create a Craft Element Criteria Model
        $query = Entry::find()
            ->sectionId($this->settings->churchesSectionId)
            ->limit(null)
            ->status(null)
            ->all();

        $data = array(
            'ids' => [],
            'gatherings' => []
        );

        Craft::info('SolidrockSync: Query for all Gathering entries', __METHOD__);

        // For each entry
        foreach ($query as $entry) {
            $gatheringId = "";

            // Get the id of this gathering
            if (isset($entry->gatheringId)) {
                $gatheringId = $entry->gatheringId;
            }

            // Add this id to our array
            $data['ids'][] = $gatheringId;

            // Add this entry id to our array, using the gathering id as the key for reference
            $data['gatherings'][$gatheringId] = $entry->id;
        }

        Craft::info('SolidrockSync: Return local Gathering data for comparison', __METHOD__);

        return $data;
    }

    public function createEntry($gathering)
    {
        $this->settings = SolidrockSync::$plugin->getSettings();

        // Create a new instance of the Craft Entry Model
        $entry = new Entry();

        // Set the section id
        $entry->sectionId = $this->settings->churchesSectionId;

        // Set the entry type
        $entry->typeId = $this->settings->churchesEntryTypeId;

        // Set the author as super admin
        $entry->authorId = 1;

        $this->saveFieldData($entry, $gathering);
    }

    public function updateEntry($entry, $gathering)
    {
        $this->saveFieldData($entry, $gathering, true);
    }

    // Private Methods
    // =========================================================================

    private function dd($data)
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
        die();
    }

    private function createGuzzleClient()
    {
        $this->client = new Client([
            'base_uri' => $this->settings->apiUrl,
            'auth' => [
                $this->settings->apiUsername,
                $this->settings->apiPassword,
            ],
            'verify' => false, // @TODO not sure why this is needed locally
            'form_params' => [
                'apiKey' => $this->settings->apiKey,
            ],
        ]);
    }

    private function saveFieldData($entry, $record, $isUpdate = false)
    {
        $gathering = $record->gathering;
        $address = $record->address;
        $social = $record->social;
        $services = $record->services;
        $images = $record->images;

        // Enabled?
        $entry->enabled = ($gathering->status_id == "1") ? true : false;

        // Set the title
        $entry->title = $gathering->name;

        // Update the title to match the gathering name
        if ($isUpdate) {
            $entry->slug = $gathering->name;
        }

        // Set the other content
        $entry->setFieldValues([
            // Basic
            'gatheringId' => $gathering->id,
            'gatheringChurchId' => $gathering->church_id ?? '',
            'gatheringChurchName' => $gathering->church_name ?? '',
            'gatheringIntro' => $gathering->profile_intro_text ?? '',
            'gatheringDescription' => $gathering->description ?? '',
            'gatheringAdditionalInfo' => $gathering->additional_info ?? '',
            'gatheringWebsiteUrl' => $gathering->website_url ?? '',
            'gatheringEmailAddress' => $gathering->office_email_address ?? '',
            'gatheringTelephoneNumber' => $gathering->office_tel_number ?? '',

            // Address
            'gatheringAddressLine1' => $address->address_line_1 ?? '',
            'gatheringAddressLine2' => $address->address_line_2 ?? '',
            'gatheringCity' => $address->city ?? '',
            'gatheringCounty' => $address->county ?? '',
            'gatheringPostcode' => $address->postcode ?? '',
            'gatheringCountry' => $address->country ?? '',
            'gatheringLatitude' => $address->lat ?? '',
            'gatheringLongitude' => $address->lng ?? '',

            // Social
            'gatheringFacebookUrl' => $social->facebook_page_url ?? '',
            'gatheringTwitterUrl' => $social->twitter_profile_url ?? '',
            'gatheringLinkedinUrl' => $social->linkedin_profile_url ?? '',
            'gatheringVimeoUrl' => $social->vimeo_url ?? '',
            'gatheringYoutubeUrl' => $social->youtube_url ?? '',
            'gatheringItunesUrl' => $social->itunes_rss_url ?? '',

            // Images
            'gatheringLogoUrl' => $images->logo[0]->file_src ?? '',
            'gatheringCoverImageUrl' => $images->cover[0]->file_src ?? '',
            'gatheringImages' => (isset($images->small) && !empty($images->small)) ? $this->prepImages($images->small) : '',

            // Services
            'gatheringServices' => (isset($services) && count($services)) ? $this->prepServices($services) : '',
        ]);

        // Save the entry!
        if (!Craft::$app->elements->saveElement($entry)) {
            Craft::error('SolidrockSync: Couldn’t save the entry "' . $entry->title . '"', __METHOD__);

            return false;
        }

        // Set now as the post date
        if (!$isUpdate) {
            $entry->postDate = new DateTime();
        }

        // Re-save the entry
        Craft::$app->elements->saveElement($entry);
    }

    private function prepServices($services)
    {
        $count = 1;
        $returnArray = [];

        foreach ($services as $service) {
            $serviceData = [
                'type' => 'service',
                'fields' => [
                    'serviceId' => $service->id ?? '',
                    'serviceName' => $service->name ?? '',
                    'serviceFrequency' => $service->frequency ?? '',
                    'serviceDay' => $service->day ?? '',
                    'serviceStartTime' => [
                        'time' => (isset($service->start_time_hour) && isset($service->start_time_minute)) ? $service->start_time_hour . ':' . $service->start_time_minute : '',
                        'timezone' => 'Europe/London',
                    ],
                    'serviceEndTime' => [
                        'time' => (isset($service->end_time_hour) && isset($service->end_time_minute)) ? $service->end_time_hour . ':' . $service->end_time_minute : '',
                        'timezone' => 'Europe/London',
                    ],
                    'serviceIsPrimaryService' => (isset($service->is_primary_service) && $service->is_primary_service === 'y') ? 1 : 0,
                    'serviceAddressLine1' => $service->address_line_1 ?? '',
                    'serviceAddressLine2' => $service->address_line_2 ?? '',
                    'serviceCity' => $service->city ?? '',
                    'serviceCounty' => $service->county ?? '',
                    'servicePostcode' => $service->postcode ?? '',
                    'serviceCountry' => $service->country ?? '',
                    'serviceLatitude' => $service->lat ?? '',
                    'serviceLongitude' => $service->lng ?? '',
                ],
            ];

            $returnArray['new' . $count] = $serviceData;

            $count++;
        }

        return $returnArray;
    }

    private function prepImages($images)
    {
        $returnArray = [];

        // Loop over each image and pull out the file_src
        foreach ($images as $i => $image) {
            // Don't include any logos in this set of images
            if ($image->is_logo !== 'y') {
                $returnArray[$i]['col1'] = $image->file_src;
            }
        }

        return $returnArray;
    }
}
