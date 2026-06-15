<?php

namespace Inovector\Mixpost\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Inovector\Mixpost\Actions\UpdateOrCreateService;
use Inovector\Mixpost\Facades\ServiceManager;

class SaveService extends FormRequest
{
    public function rules(): array
    {
        $default = [
            'active' => ['required', 'boolean'],
        ];

        $name = $this->route('service');
        $secrets = $this->service()::$secretFormAttributes;

        $formRules = $this->service()::formRules();
        $modifiedFormRules = array_reduce(array_keys($formRules), function ($carry, $key) use ($formRules, $name, $secrets) {
            $rule = $formRules[$key];

            // Secret attributes are write-only: once stored they may be submitted blank
            // to keep the current value, so they must not be `required` on update.
            if (in_array($key, $secrets, true) && ! empty(ServiceManager::get($name, "configuration.$key"))) {
                $rule = ['nullable'];
            }

            $carry["configuration.$key"] = $rule;

            return $carry;
        }, []);

        return array_merge(
            $default,
            $modifiedFormRules
        );
    }

    public function handle(): void
    {
        $name = $this->route('service');
        $secrets = $this->service()::$secretFormAttributes;

        $configuration = Arr::map($this->service()::form(), function ($_, $key) use ($name, $secrets) {
            $input = $this->input("configuration.$key");

            // Keep the currently stored secret when the write-only field is left blank.
            if (in_array($key, $secrets, true) && ($input === null || $input === '')) {
                return ServiceManager::get($name, "configuration.$key");
            }

            return $input;
        });

        (new UpdateOrCreateService)(
            name: $this->route('service'),
            configuration: $configuration,
            active: $this->input('active', false)
        );
    }

    public function messages(): array
    {
        $formMessages = $this->service()::formMessages();

        return array_reduce(array_keys($formMessages), function ($carry, $key) use ($formMessages) {
            $carry["configuration.$key"] = $formMessages[$key];

            return $carry;
        }, []);
    }

    protected function service(): ?string
    {
        return ServiceManager::getServiceClass($this->route('service'));
    }
}
