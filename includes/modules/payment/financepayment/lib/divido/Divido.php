<?php
// if (!function_exists('curl_init')) {
//   throw new Exception('Divido needs the CURL PHP extension.');
// }
// if (!function_exists('json_decode')) {
//   throw new Exception('Divido needs the JSON PHP extension.');
// }
// if (!function_exists('mb_detect_encoding')) {
//   throw new Exception('Divido needs the Multibyte String PHP extension.');
// }

use GuzzleHttp\Client as Guzzle;

class FinanceApi
{
    
    public function getGlobalSelectedPlans( $api_key, $finance_plan)
    {
        $all_plans = $this->getAllPlans( $api_key ); 
        $selected_plans = explode(', ', $finance_plan );
        if (!$selected_plans) {
            return array();
        }

        $plans = array();
        foreach ($all_plans as $plan) {
            if (in_array($plan->text, $selected_plans)) {
                $plans[$plan->id] = $plan;
            }
        }

      return $plans;
    }


    public function getFinanceEnv($api_key){

        if (!$api_key) {
            return array();
        }

        $client = new Guzzle();
        $env = $this->getEnvironment($api_key);
        
        $httpClientWrapper = new \Divido\MerchantSDK\HttpClient\HttpClientWrapper(
            new \Divido\MerchantSDKGuzzle6\GuzzleAdapter($client),
            \Divido\MerchantSDK\Environment::CONFIGURATION[$env]['base_uri'],
            $api_key
        );

        $sdk  = new \Divido\MerchantSDK\Client( $httpClientWrapper, $env );
        

        $response = $sdk->platformEnvironments()->getPlatformEnvironment();
        $finance_env = $response->getBody()->getContents();
        $decoded =json_decode($finance_env);

        return $decoded->data->environment;


    }

    public function  getAllPlans( $api_key )
    {
        
        $env               = $this->getEnvironment( $api_key );
        $client            = new \GuzzleHttp\Client();

        $httpClientWrapper = new \Divido\MerchantSDK\HttpClient\HttpClientWrapper(
            new \Divido\MerchantSDKGuzzle6\GuzzleAdapter($client),
            \Divido\MerchantSDK\Environment::CONFIGURATION[$env]['base_uri'],
            $api_key
        );

        $sdk = new \Divido\MerchantSDK\Client($httpClientWrapper, $env);
        $request_options = ( new \Divido\MerchantSDK\Handlers\ApiRequestOptions() );

        $plans = $sdk->getAllPlans( $request_options );
        $plans = $plans->getResources();
        
        try {
            $plans_plain = array();
            foreach ($plans as $plan) {
                $plan_copy = new stdClass();
                $plan_copy->id = $plan->id;
                $plan_copy->text = $plan->description;
                $plan_copy->country = $plan->country;
                $plan_copy->min_amount = $plan->credit_amount->minimum_amount;
                $plan_copy->min_deposit = $plan->deposit->minimum_percentage;
                $plan_copy->max_deposit = $plan->deposit->maximum_percentage;
                $plan_copy->interest_rate = $plan->interest_rate_percentage;
                $plan_copy->deferral_period = $plan->deferral_period_months;
                $plan_copy->agreement_duration = $plan->agreement_duration_months;

                $plans_plain[$plan->id] = $plan_copy;
            }
            return $plans_plain;
        } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $e) {
           echo $e->getMessage();
        }
    }

    public function getEnvironment( $key ) {
        $array       = explode( '_', $key );
        $environment = strtoupper( $array[0] );
        switch ($environment) {
            case 'LIVE':
                return constant( 'Divido\MerchantSDK\Environment::' . $environment );

            case 'SANDBOX':
                return constant( "Divido\MerchantSDK\Environment::$environment" );
            
            default:
                return constant( "Divido\MerchantSDK\Environment::SANDBOX" );
            
        }

    }

    function activate( $api_key, $application_id, $order_total, $order_id, $shipping_method = null, $tracking_numbers = null ) {
        // First get the application you wish to create an activation for.
        $application = ( new \Divido\MerchantSDK\Models\Application() )
        ->withId( $application_id );
        $items       = [
            [
                'name'     => "Order id: $order_id",
                'quantity' => 1,
                'price'    => $order_total * 100,
            ],
        ];
        // Create a new application activation model.
        $application_activation = ( new \Divido\MerchantSDK\Models\ApplicationActivation() )
            ->withOrderItems( $items )
            ->withDeliveryMethod( $shipping_method )
            ->withTrackingNumber( $tracking_numbers );
        // Create a new activation for the application.

        $env                      = $this->getEnvironment( $api_key );
        $client 				  = new \GuzzleHttp\Client();
        $httpClientWrapper 		  = new \Divido\MerchantSDK\HttpClient\HttpClientWrapper(
                                    new \Divido\MerchantSDKGuzzle6\GuzzleAdapter($client),
                                    \Divido\MerchantSDK\Environment::CONFIGURATION[$env]['base_uri'],
                                    $api_key
        );
        $sdk                      = new \Divido\MerchantSDK\Client( $httpClientWrapper, $env );
        $response                 = $sdk->applicationActivations()->createApplicationActivation( $application, $application_activation );
        $activation_response_body = $response->getBody()->getContents();
    }
}


    