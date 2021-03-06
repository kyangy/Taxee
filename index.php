<?php

	/*
	* The hosted version of Taxee has all
	* static assets hosted in a CDN.  Create a
	* CDNBASE environment variable that holds
	* the path to the CDN to utilize
	*/
	function getCDNPath()
	{
		if (getenv("CDNBASE") != "")
		{
		    $version_file = json_decode(file_get_contents("../../version.json"));
		    return getenv("CDNBASE") . $version_file->version . "/" . "api/";
		}
		else
		{
		    return dirname(__FILE__) . "/";
		}
	}


	define("CDN_URL", getCDNPath());

	require 'vendor/autoload.php';
	require 'models/TaxCalculator.php';
	require 'models/StateTaxCalculator.php';
	require 'models/FederalTaxCalculator.php';

	$state_calculator = new StateTaxCalculatorModel();
	$federal_calculator = new FederalTaxCalculatorModel();

	$app = new \Slim\Slim();

	// Parse the response and display it.
	$app->hook('respond', function ($response) use ($app) {
		$app->response->header('Access-Control-Allow-Origin', '*');
		$app->response->headers->set('Content-Type', 'application/json');

		if ($response['success'] === false)
		{
			$app->halt(400, "{\"success\": false, \"reason\": \"" . $response['reason'] . "\"}");
		}
		else
		{
			echo json_encode($response['data']);
		}
	});

	$app->get('/v1/federal/:year/', function ($year) use ($app, $federal_calculator) {
		$response = $federal_calculator->get_federal_data($year);
		$app->applyHook('respond', $response);
	});

	$app->get('/v1/state/:state/:year/', function ($year, $state) use ($app, $state_calculator) {
		$response = $state_calculator->get_state_data($year, $state);
	    $app->applyHook('respond', $response);
	});

	$app->post('/v1/calculate/:year/', function ($year) use ($app, $state_calculator, $federal_calculator) {
	    $pay_rate 		= $app->request->post('pay_rate');
	    $pay_periods 	= ($app->request->post('pay_periods') == false) ? 1 : $app->request->post('pay_periods');
	    $filing_status 	= $app->request->post('filing_status');
	    $state 			= $app->request->post('state');

	    if (!isset($pay_rate) || !isset($filing_status))
	    {
	    	$response['success'] = false;
	    	$response['reason'] = "Missing required parameters";
	    }
	    else
	    {
	    	$federal_response = $federal_calculator->calculate($year, $pay_rate, $pay_periods, $filing_status, $state);
	    	if (isset($state)) {
	    		$state_response = $state_calculator->calculate($year, $pay_rate, $pay_periods, $filing_status, $state);
	    		$response['data']['annual'] = array_merge($state_response['data'], $federal_response['data']);
	    	} else {
	    		$response['data']['annual'] = $federal_response['data'];
	    	}

	    	if ($pay_periods > 1) {
	    		$response['data']['per_pay_period'] = array();
		    	foreach ($response['data']['annual'] as $key=>$value)
		    	{
		    		$newVal = $value['amount'] / $pay_periods;
		    		$newVal = (float) number_format($newVal, 2, '.', '');
		    		$response['data']['per_pay_period'][$key] = array();
		    		$response['data']['per_pay_period'][$key]['amount'] = $newVal;
		    	}
		    }

	    	$response['success'] = true;
	    }

	   $app->applyHook('respond', $response);
	});

	$app->run();
?>