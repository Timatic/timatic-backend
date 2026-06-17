<?php

namespace App\Http\Requests\Traits;

trait OptionalPatchParameters
{
    /**
     * @param  array<string,mixed>  $rules
     * @param  array<string>  $except
     * @return array<string,string|mixed>
     */
    protected function addPatchOptionalValidation(array $rules, array $except = []): array
    {
        if ($this->method() == 'PATCH') {
            $newRules = [];

            foreach ($rules as $name => $value) {
                if (in_array($name, $except)) {
                    $newRules[$name] = $value;

                    continue;
                }

                if (is_array($value)) {
                    array_unshift($value, 'sometimes');

                    $newRules[$name] = $value;
                } else {
                    $newRules[$name] = 'sometimes|'.$value;
                }
            }

            $rules = $newRules;
        }

        return $rules;
    }
}
