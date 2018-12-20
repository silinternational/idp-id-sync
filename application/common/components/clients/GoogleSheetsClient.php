<?php
namespace Sil\Idp\IdSync\common\components\clients;

use InvalidArgumentException;
use Sil\Idp\IdSync\common\models\User;
use yii\base\Component;
use yii\helpers\Json;

class GoogleSheetsClient extends Component
{
    /**
     * @var null|string The Application Name to use with Google_Client.
     */
    public $applicationName = null;
    
    /**
     * @var null|string The path to the JSON file with authentication
     *     credentials from Google.
     */
    public $jsonAuthFilePath = null;
    
    /**
     * @var null|string The JSON authentication credentials from Google.
     */
    public $jsonAuthString = null;
    
    /**
     * @var null|string The Spreadsheet ID.
     */
    public $spreadsheetId = null;
    
    /**
     * @var array<string> OAuth Scopes needed for reading/writing sheets.
     */
    public $scopes = [\Google_Service_Sheets::SPREADSHEETS];
    
    /**
     * @var \Google_Service_Sheets
     */
    private $sheets = null;
    
    /**
     * Init and ensure required properties are set
     */
    public function init()
    {
        if (! empty($this->jsonAuthFilePath)) {
            if (file_exists($this->jsonAuthFilePath)) {
                $this->jsonAuthString = \file_get_contents($this->jsonAuthFilePath);
            } else {
                throw new InvalidArgumentException(sprintf(
                    'JSON auth file path of %s provided, but no such file exists.',
                    var_export($this->jsonAuthFilePath, true)
                ), 1497547815);
            }
        }
        $requiredProperties = [
            'applicationName',
            'jsonAuthString',
            'spreadsheetId',
        ];
        foreach ($requiredProperties as $requiredProperty) {
            if (empty($this->$requiredProperty)) {
                throw new InvalidArgumentException(sprintf(
                    'No %s was provided.',
                    $requiredProperty
                ), 1495648880);
            }
        }
        
        parent::init();
    }
    
    protected function initGoogleClient()
    {
        $jsonCreds = Json::decode($this->jsonAuthString);
        $googleClient = new \Google_Client();
        $googleClient->setApplicationName($this->applicationName);
        $googleClient->setScopes($this->scopes);
        $googleClient->setAuthConfig($jsonCreds);
        $googleClient->setAccessType('offline');
        $this->sheets = new \Google_Service_Sheets($googleClient);
    }
    
    /**
     * Get information about ALL of the users (active or not).
     *
     * @return array[] A list of users' information.
     */
    public function getAllUsersInfo(): array
    {
        $allUsersInfo = [];
        $start = 2;
        $howMany = 100;
        
        $hasAllUsers = false;
        while (! $hasAllUsers) {
            $batch = $this->getUsersInfoFromSpreadsheet($start, $howMany);
            $allUsersInfo = array_merge($allUsersInfo, $batch);
            $start += $howMany;
            
            if (count($batch) < $howMany) {
                $hasAllUsers = true;
            }
        }
        
        return $allUsersInfo;
    }
    
    /**
     * @param int $startRow
     * @param int $howMany
     * @return array
     */
    protected function getUsersInfoFromSpreadsheet(
        int $startRow = 2,
        int $howMany = 100
    ) {
        if (! $this->sheets instanceof \Google_Service_Sheets) {
            $this->initGoogleClient();
        }
        
        $users = [];
        $currentRow = $startRow;
        $range = sprintf('Users!A%s:L%s', $startRow, $startRow + $howMany - 1);
        $rows = $this->sheets->spreadsheets_values->get($this->spreadsheetId, $range, ['majorDimension' => 'ROWS']);
        if (isset($rows['values'])) {
            foreach ($rows['values'] as $user) {
                /*
                 * If first column is empty, consider it as no more records
                 */
                if (empty($user[0])) {
                    break;
                }
                
                // NOTE:
                // Trailing empty cells are not returned by Google Sheets.
                // Intermediate empty cells come back as empty strings, so an
                // empty column could be absent or "". Handle both situations.
                
                $users[] = [
                    User::EMPLOYEE_ID => $user[0],
                    User::FIRST_NAME => $user[1],
                    User::LAST_NAME => $user[2],
                    User::DISPLAY_NAME => $user[3],
                    User::USERNAME => $user[4],
                    User::EMAIL => $user[5],
                    User::ACTIVE => $user[6] ?? 'yes',
                    User::LOCKED => $user[7] ?? 'no',
                    'last_synced' => $this->getValueIfNonEmpty($user, 8),
                    User::REQUIRE_MFA => $user[9] ?? 'no',
                    User::MANAGER_EMAIL => $this->getValueIfNonEmpty($user, 10),
                    User::SPOUSE_EMAIL => $this->getValueIfNonEmpty($user, 11),
                ];
                
                /*
                 * Update last_synced column in spreadsheet
                 */
                $updateRange = 'I'.$currentRow;
                $updateBody = new \Google_Service_Sheets_ValueRange([
                    'range' => $updateRange,
                    'majorDimension' => 'ROWS',
                    'values' => ['values' => date('c')],
                ]);
                $this->sheets->spreadsheets_values->update(
                    $this->spreadsheetId,
                    $updateRange,
                    $updateBody,
                    ['valueInputOption' => 'USER_ENTERED']
                );
                
                $currentRow++;
            }
        }
        
        return $users;
    }
    
    /**
     * Get the value in the array at the specified index (or key). If empty,
     * return null.
     *
     * @param array $array The array to get the value from.
     * @param int|string $index The index (or key) whose value is desired.
     * @return mixed|null The resulting non-empty value, or null.
     */
    protected function getValueIfNonEmpty($array, $index)
    {
        if (empty($array[$index])) {
            return null;
        }
        return $array[$index];
    }
}