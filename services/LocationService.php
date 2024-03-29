<?php


namespace app\services;


use app\models\City;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Yii;
use yii\helpers\ArrayHelper;

class LocationService
{

    private Client $client;
    private string $geoApiUri;
    private mixed $geoApiKey;
    private string $userCity;

    public function __construct() {
        $this->geoApiKey = Yii::$app->params['apiYandexGeocoderKey'];
        $this->geoApiUri = Yii::$app->params['geoApiUri'];
        $this->userCity = Yii::$app->user->identity->city->name;

        $this->client = new Client();
    }

    /**
     * Main location function.
     * @param string $geocode - address string like 'Абаза, Спортивная 4'
     * @return array - address values with city name, address and location lat-long
     * @throws \Exception
     */
    public function getLocation(string $geocode): array
    {
        $result = [];

        try {
            $response = $this->client->request('GET', $this->geoApiUri, [
                'query' => ['apikey' => $this->geoApiKey, 'geocode' => $geocode, 'format' => 'json']
            ]);
            $content = $response->getBody()->getContents();
            $responseData = json_decode($content, true);
            $geoObjects = ArrayHelper::getValue($responseData, 'response.GeoObjectCollection.featureMember');
            foreach ($geoObjects as $geoObject) {
                $result[] = $this->getLocationData($geoObject);
            }

        } catch (GuzzleException $e) {
            echo('Ошибка получения гео-данных');
        }

        //for only user city
        return array_values(array_filter($result, fn($item) => $item['city'] === $this->userCity));

        // for all cities
//        return array_values($result);
    }

    /**
     * parsing geo-data array object for getting necessary location data
     * @param array $geoObject
     * @return array
     * @throws \Exception
     */
    private function getLocationData(array $geoObject): array
    {
        $geocoderMetaData = ArrayHelper::getValue($geoObject, 'GeoObject.metaDataProperty.GeocoderMetaData');
        $addressComponents = ArrayHelper::map(
            ArrayHelper::getValue($geocoderMetaData, 'Address.Components'),
            'kind',
            'name'
        );

        $location = ArrayHelper::getValue($geocoderMetaData, 'text');
        $city = ArrayHelper::getValue($addressComponents, 'locality');
        $address = ArrayHelper::getValue($geoObject, 'GeoObject.name');
        $coords = explode(' ', ArrayHelper::getValue($geoObject, 'GeoObject.Point.pos'));
        $long = $coords[0];
        $lat = $coords[1];

        return [
            'location' => $location,
            'city' => $city,
            'address' => $address,
            'lat' => $lat,
            'long' => $long,
        ];
    }

    /**
     * Checking choosed task city in DB
     * @param string $cityName
     * @return bool
     */
    public function isCityExistsInDB(string $cityName): bool
    {
        return City::find()->where(['name' => $cityName])->exists();
    }

    /**
     * Getting chosen task city id
     * @param string $cityName
     * @return int
     */
    public function getCityIdByName(string $cityName): int
    {
        $city = City::find()->where(['name' => $cityName])->limit(1)->one();
        return $city->id;
    }

    /**
     * Getting city name from db city_id
     * @param string $id
     * @return string
     */
    public static function getCityNameById(string $id): string
    {
        $city = City::find()->where(['id' => $id])->limit(1)->one();
        return $city->name;
    }
}