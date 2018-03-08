<?php

namespace WordPressBimserver;


class GravityForms {
	const FIELD_UPLOAD = 'GF_Field_FileUpload';
	const FIELD_HIDDEN = 'GF_Field_Hidden';

	/** @var array $entry */
	protected $entry;
	/** @var array $form */
	protected $form;
	/** @var \GF_Field_FileUpload $uploadField */
	protected $uploadField = null;
	/** @var \GF_Field_Hidden $hiddenField */
	protected $hiddenField = null;


	public function __construct(array $entry, array $form) {
		$this->entry= $entry;
		$this->form = $form;
		$this->validateForm();
	}

	public function getUploadFileName(): string {
		$this->populateFields();
		return end(explode('/', $this->entry[$this->uploadField->id]));
	}

	public function getUploadFileContent(): string {
		$this->populateFields();
		return file_get_contents(str_replace(bloginfo('wpurl'), get_home_path(), $this->entry[$this->uploadField->id]));
	}

	public function processServiceResponse(string $response) {
		$this->populateFields();
		$this->entry[$this->hiddenField->id] = $response;
		\GFAPI::update_entry($this->entry);
	}

	protected function validateForm() {
		foreach (['title', 'fields', 'id'] as $key) {
			if (!isset($this->form[$key])) {
				throw new \Exception(vsprintf('Missing key `%s` in gravity forms form.', [$key]));
			}
		}

		if (!is_array($this->form['fields'])) {
			throw new \Exception('Invalid fields in gravity forms form.');
		}
	}

	protected function populateFields() {
		if ($this->uploadField === null) {
			$this->uploadField = $this->findField(self::FIELD_UPLOAD);
		}
		if ($this->hiddenField === null) {
			$this->hiddenField = $this->findField(self::FIELD_HIDDEN);
		}
	}

	protected function findField(string $fieldType): \GF_Field {
		foreach ($this->form['fields'] as $field) {
			if (is_a($field, $fieldType)) {
				return $field;
			}
		}
		throw new \Exception(vsprintf('Could not find field of type `%s` in form `%s`.', []));
	}
}