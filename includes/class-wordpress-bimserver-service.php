<?php

namespace WordPressBimserver;


class Service {
	/** @var string $ifcContent */
	protected $ifcContent;


	public function __construct(string $ifcContent) {
		$this->ifcContent = $ifcContent;
	}

	public function submit(): string {
		$options = WordPressBimserver::getOptions();
		$curlHandler = curl_init($options['url'] . '/' . $options['service_id']);
		$headers = [
			vsprintf('Authorization: Bearer %s', [$options['token']]),
			vsprintf('Input-Type: %s', [$options['input_type']]),
			vsprintf('Output-Type: %s', [$options['output_type']]),
			'Expect:', // To prevent the expect 100
		];
		curl_setopt_array($curlHandler, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $this->ifcContent,
			CURLOPT_ENCODING => 'gzip, deflate',
		]);
		curl_setopt($curlHandler, CURLINFO_HEADER_OUT, true);

		$response = curl_exec($curlHandler);
		curl_close($curlHandler);
		if ($response === '') {
			throw new \Exception('Empty response from Bimserver.');
		}
		return $response;
	}
}