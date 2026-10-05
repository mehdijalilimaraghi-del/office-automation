<?php

namespace App\Http\Requests\Core\Departments;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
            ],

            'manager_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'name' => [
                'required',
                'string',
                'max:150',
            ],

            
            'icon' => [
                'nullable',
                'string',
                'max:50',
                'in:building-2,building,landmark,briefcase,users,users-round,user-round,user-cog,factory,warehouse,store,shopping-cart,truck,package,boxes,handshake,calculator,banknote,dollar-sign,receipt-text,scale,gavel,file-text,files,folder,folder-open,archive,clipboard,clipboard-list,clipboard-check,list-checks,check-circle-2,monitor,laptop,server,database,code-2,wrench,hard-hat,cog,settings,shield,shield-check,lock-keyhole,key-round,megaphone,radio,headphones,phone,mail,globe,map-pin,route,calendar-days,clock-3,target,bar-chart-3,pie-chart,trending-up,tag,search,printer,scan-line,ruler,book-open,home',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'custom_fields' => ['nullable', 'array'],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'parent_id' => 'دپارتمان والد',
            'manager_id' => 'مدیر دپارتمان',
            'name' => 'نام دپارتمان',
                        'icon' => 'آیکون واحد',
            'description' => 'توضیحات',
            'is_active' => 'وضعیت فعال بودن',
        ];
    }
}
