<?php

namespace App\Modules\Users\Http\Requests;

use App\Services\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;

class AddressRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'firstname' => 'required',
            'lastname' => 'required',
            'country' => 'required',
            'state' => 'required',
            'city' => 'required',
            'street' => 'required',
            'postalcode' => 'required',
            'iphone' => 'required'
        ];
    }

    /**
     * 自定义验证信息
     *
     * @return array
     */
    public function messages()
    {
        return [
            'firstname.required' => 'firstname can not be null',
            'lastname.required' => 'lastname can not be null',
            'country.required' => 'country can not be null',
            'state.required' => 'state can not be null',
            'city.required' => 'city can not be null',
            'street.required' => 'street can not be null',
            'postalcode.required' => 'postcode can not be null',
            'iphone.required' => 'phone number can not be null',
        ];
    }

    /**
     * 自定义错误数组
     *
     * @return array
     */
    public function formatErrors(Validator $validator)
    {
        $errors = $validator->errors()->first();
        return ApiResponse::failure(g_API_ERROR, $errors);
    }
}
