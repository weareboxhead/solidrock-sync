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
use craft\elements\Category;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use GuzzleHttp\Client;

/**
 * Jobs Service
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
class Jobs extends Component
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

        // Get all Solidrock Jobs
        $response = $this->client->request('POST', 'jobs/all_open_jobs.json', [
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
        if (count(get_object_vars($body->jobs)) === 0) {
            Craft::error('SolidrockSync: No results from API Request', __METHOD__);

            return false;
        }

        $data = array(
            'ids' => [],
            'jobs' => []
        );

        // For each Job
        foreach ($body->jobs as $jobId => $job) {
            // Add this id to our array
            $data['ids'][] = $jobId;

            // Add this job to our array, using the id as the key
            $data['jobs'][$jobId] = $job;
        }

        Craft::info('SolidrockSync: Finished getting remote data', __METHOD__);

        return $data;
    }

    public function getLocalData()
    {
        Craft::info('SolidrockSync: Get local Job data', __METHOD__);

        if (!\is_array($this->settings)) {
            $this->settings = SolidrockSync::$plugin->getSettings();
        }

        // Create a Craft Element Criteria Model
        $query = Entry::find()
            ->sectionId($this->settings->jobsSectionId)
            ->limit(null)
            ->status(null)
            ->all();

        $data = array(
            'ids' => [],
            'jobs' => []
        );

        Craft::info('SolidrockSync: Query for all Job entries', __METHOD__);

        // For each entry
        foreach ($query as $entry) {
            $jobId = "";

            // Get the id of this job
            if (isset($entry->jobId)) {
                $jobId = $entry->jobId;
            }

            // Add this id to our array
            $data['ids'][] = $jobId;

            // Add this entry id to our array, using the job id as the key for reference
            $data['jobs'][$jobId] = $entry->id;
        }

        Craft::info('SolidrockSync: Return local data for comparison', __METHOD__);

        return $data;
    }

    public function createEntry($job)
    {
        $this->settings = SolidrockSync::$plugin->getSettings();

        // Create a new instance of the Craft Entry Model
        $entry = new Entry();

        // Set the section id
        $entry->sectionId = $this->settings->jobsSectionId;

        // Set the entry type
        $entry->typeId = $this->settings->jobsEntryTypeId;

        // Set the author as super admin
        $entry->authorId = 1;

        $this->saveFieldData($entry, $job);
    }

    public function updateEntry($entry, $job)
    {
        $this->settings = SolidrockSync::$plugin->getSettings();

        $this->saveFieldData($entry, $job, true);
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
        $job = $record->job;
        $categories = $record->job_categories;

        // Enabled?
        $entry->enabled = ($job->status === "public") ? true : false;

        // Set the title
        $entry->title = $job->title;

        // Update the title to match the job title
        if ($isUpdate) {
            $entry->slug = $job->title;
        }

        // Get Craft church entry by gathering_id number
        if (isset($job->gathering_id)) {
            $church = Entry::find()
                ->sectionId($this->settings->churchesSectionId)
                ->search('gatheringId::' . $job->gathering_id)
                ->status(null)
                ->one();
        }

        // Set the other content
        $entry->setFieldValues([
            'jobId' => $job->id,
            'jobChurchGathering' => (isset($church) && $church) ? [$church->id] : [],
            'jobCategories' => (isset($categories) && !empty($categories)) ? $this->parseCategories($categories) : [],
            'jobReference' => $job->reference_code ?? '',
            'jobType' => $job->type ?? '',
            'jobContractLength' => $job->contract_length ?? '',
            'jobSalary' => $job->salary ?? '',
            'jobDescription' => $job->description ?? '',
            'jobQualitiesGifts' => $job->required_skills ?? '',
            'jobRightToWorkInUk' => (isset($job->right_to_work_in_uk) && $job->right_to_work_in_uk === 'y') ? 1 : 0,
            'jobAcceptanceOfFiecDoctrinalBasis' => (isset($job->acceptance_of_fiec_doctrinal_basis) && $job->acceptance_of_fiec_doctrinal_basis === 'y') ? 1 : 0,
            'jobAgreementWithFiecEthosStatements' => (isset($job->agreement_with_fiec_ethos_statements) && $job->agreement_with_fiec_ethos_statements === 'y') ? 1 : 0,
            'jobContactName' => $job->contact_name ?? '',
            'jobContactPosition' => $job->contact_position ?? '',
            'jobContactEmailAddress' => $job->contact_email_address ?? '',
            'jobContactTelephoneNumber' => $job->contact_tel_number ?? '',
            'jobOpportunitiesProblems' => $job->opportunities_problems ?? '',
            'jobHousingFinancialSupport' => $job->housing_financial_support ?? '',
            'jobPastoralResponsibilities' => $job->pastoral_responsibilities ?? '',
            'jobAdditionalInfo' => $job->additional_info ?? '',
        ]);

        // Set the date_listing_posted date as post date
        $entry->postDate = DateTimeHelper::toDateTime(strtotime($job->date_listing_posted));

        // Set the expiry date
        $entry->expiryDate = DateTimeHelper::toDateTime(strtotime($job->date_listing_expires));

        // Save the entry!
        if (!Craft::$app->elements->saveElement($entry)) {
            Craft::error('SolidrockSync: Couldn’t save the entry "' . $entry->title . '"', __METHOD__);

            return false;
        }
        // // Re-save the entry
        // Craft::$app->elements->saveElement($entry);
    }

    private function parseCategories($jobCategories)
    {
        // If there is no category group specified, don't do this
        if (!$this->settings->jobsCategoryGroupId) {
            return [];
        }

        // Are thre any categories even assigned?
        if (!$jobCategories) {
            return [];
        }

        // Get all existing categories
        $existingCategories = [];

        // Create a Craft Element Criteria Model
        $query = Category::find()
            ->groupId($this->settings->jobsCategoryGroupId)
            ->all();

        // For each category
        foreach ($query as $existingCategory) {
            // Add its id to our array
            $existingCategories[$existingCategory->id] = $existingCategory->jobCategoryId;
        }

        $returnIds = [];

        // Loop over categories assigned to the job
        foreach ($jobCategories as $jobCategory) {
            $categoryExists = false;

            $key = array_search($jobCategory->id, $existingCategories);

            if ($key) {
                $returnIds[] = $key;
                $categoryExists = true;
            }

            // Do we need to create the Category?
            if (!$categoryExists) {
                // Create the category
                $newCategory = new Category();

                $newCategory->title = $jobCategory->title;
                $newCategory->groupId = $this->settings->jobsCategoryGroupId;

                $newCategory->setFieldValues([
                    'jobCategoryId' => $jobCategory->id,
                ]);

                // Save the category!
                if (!Craft::$app->elements->saveElement($newCategory)) {
                    Craft::error('SolidrockSync: Couldn’t save the category "' . $newCategory->title . '"', __METHOD__);

                    return false;
                }

                $returnIds[] = $newCategory->id;
            }
        }

        return $returnIds;
    }
}
