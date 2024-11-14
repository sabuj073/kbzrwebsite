<?php

namespace App\Models;

use App\Models\Brand;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

//class ProductsImport implements ToModel, WithHeadingRow, WithValidation
class BrandsImport implements ToCollection, WithHeadingRow, ToModel
{
    private $rows = 0;

    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            Brand::create([
                'name' => $row['name'],
                'logo' => $this->downloadLogo($row['logo']),
                'meta_title' => $row['meta_title'],
                'meta_description' => $row['meta_description'],
            ]);
        }

        flash(translate('Brands imported successfully'))->success();
        
    }

    public function model(array $row)
    {
        ++$this->rows;
    }

    public function downloadLogo($url)
    {
        try {
            $upload = new Upload;
            $upload->external_link = $url;
            $upload->type = 'image';
            $upload->save();

            return $upload->id;
        } catch (\Exception $e) {
        }
        return null;
    }
}
