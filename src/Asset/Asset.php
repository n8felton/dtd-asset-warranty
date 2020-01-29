<?php

require_once __DIR__ . '/../../vendor/autoload.php';

class Asset
{
    /**
     * Assets returned from the DTD API
     *
     * @var array
     */
    private $assets;

    /**
     * API REST Client
     *
     * @var string
     */
    private $apiRestClient;

    /**
     * API domain
     *
     * @var string
     */
    public $apiDomain = 'https://apigtwb2c.us.dell.com';

    /**
     * Get access token url to retrieve token
     *
     * @return string
     */
    public function getBaseAccessTokenUrl()
    {
        return $this->apiDomain . '/auth/oauth/v2/token';
    }

    /**
     * Get base URL for accessing API endpoints
     *
     * @return string
     */
    public function getBaseApiEndpointUrl()
    {
        // https://apigtwb2c.us.dell.com/PROD/sbil/eapi/v5
        return $this->apiDomain . '/PROD/sbil/eapi/v5';
    }

    /**
     * Get get full endpoint URL
     *
     * @param string
     * 
     * @return string
     */
    public function getApiEndpointUrl(string $endpoint)
    {
        // https://apigtwb2c.us.dell.com/PROD/sbil/eapi/v5/assets
        return $this->getBaseApiEndpointUrl() . '/' . $endpoint;
    }

    public function __construct()
    {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        $dotenv->required('CLIENT_ID')->notEmpty();
        $dotenv->required('CLIENT_ID')->notEmpty();

        $provider = new \League\OAuth2\Client\Provider\GenericProvider([
            'clientId' => getenv('CLIENT_ID'),
            'clientSecret' => getenv('CLIENT_SECRET'),
            'redirectUri' => 'https://techdirect.dell.com',
            'urlAuthorize' => '',
            'urlAccessToken' => $this->getBaseAccessTokenUrl(),
            'urlResourceOwnerDetails' => '',
        ]);

        try {
            // Try to get an access token using the client credentials grant.
            $accessToken = $provider->getAccessToken('client_credentials');
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            // Failed to get the access token
            exit($e->getMessage());
        }
        // var_dump($accessToken->jsonSerialize());
        $this->apiRestClient = new RestClient([
            'base_url' => $this->getBaseApiEndpointUrl(),
            'headers' => ['Authorization' => 'Bearer ' . $accessToken]
        ]);
    }

    public function get(string $endpoint, string $serviceTags, array $params)
    {
        // TODO: Verify $endpoint is a valid option
        $options = ['assets', 'asset-entitlements', 'asset-components', 'asset-entitlement-components'];
        $result = $this->apiRestClient->get($endpoint, $params);
        if ($result->info->http_code == 200) {
            $response = $result->decode_response();
            if (is_array($response)) {
                // Convert the indexed array to an associative array using serviceTag
                $this->assets = array_column($response, null, 'serviceTag');
            } else {
                $this->assets = array($serviceTags => $response);
            }
        }
    }

    public function getNumAssets()
    {
        return count($this->assets);
    }

    public function getAssets(string $serviceTags)
    {
        $this->get("assets", $serviceTags, ['servicetags' => $serviceTags]);
        return $this->assets;
    }

    public function getAssetsWarranty(string $serviceTags)
    {
        $this->get("asset-entitlements", $serviceTags, ['servicetags' => $serviceTags]);
        return $this->assets;
    }

    public function getAssetsDetails(string $serviceTags)
    {
        $this->get("asset-components", $serviceTags, ['servicetags' => $serviceTags]);
        return $this->assets;
    }

    public function getAssetSummary(string $serviceTag)
    {
        $this->get("asset-entitlement-components", $serviceTag, ['servicetag' => $serviceTag]);
        return $this->assets;
    }

    public function getShipDate(string $serviceTags)
    {
        $this->getAssets($serviceTags);
        return array_column($this->assets, 'shipDate', 'serviceTag');
    }

    public function getSystemDescription(string $serviceTags)
    {
        $this->getAssets($serviceTags);
        return array_column($this->assets, 'systemDescription', 'serviceTag');
    }

    public function getModel(string $serviceTags)
    {
        return $this->getSystemDescription($serviceTags);
    }

    public function getWarrantyEndDate(string $serviceTags)
    {
        $warranties = $this->getAssetsWarranty($serviceTags);
        foreach ($warranties as $warranty) {
            $endDate = "";
            foreach ($warranty->entitlements as $entitlement) {
                if ($entitlement->endDate > $endDate) {
                    $endDate = $entitlement->endDate;
                    $assets[$warranty->serviceTag] = $entitlement->endDate;
                }
            }
        }
        return $assets;
    }
}
