<?php

namespace App\Http\Controllers;

use App\Http\Traits\ResponseTrait;
use App\Http\Traits\ServiableTrait;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SectionController extends Controller
{
    //
    use ServiableTrait;


    public static function find(int $id)
    {
        $section = Section::find($id);
        $section->services;
        $section->path;
        $section->audit;
        $section->dossiers;
        $section->fichiers;


        return $section;
    }


    public function get_ss()
    {
       $ss = Section::all();

       foreach ($ss as $key => $s) {
        # code...

        $s->services;

       }

       return $ss;
    }

    public function add_section(Request $request)
    {
        DB::beginTransaction();


        try
        {

            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
                'services' => ['required', 'json'],
            ]);

            if (!(int)Auth::id())  throw new \Exception("Vous etes pas Administrateur !");

            $section = new Section(
                [
                    "name" => $request->name,
                ]
            );

            if (!empty($request->description)) $section->description = $request->description;

            $section->push();
            $section->refresh();

            $section->path()->create(
                [
                    "value" => $request->name
                ]
            );

            $services = json_decode( $request->services );

            $this->add_to_services($services, $section->id, "App\\Models\\Section");

            $section->refresh();

            Storage::makeDirectory("public\\{$section->path->value}");

        }
        catch (\Throwable $th)
        {
            DB::rollBack();

            return ResponseTrait::get_error($th);

        }

        DB::commit();

        return ResponseTrait::get_success($section);
    }

}
