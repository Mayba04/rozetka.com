<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CategoryController extends Controller
{
    public function getList()
    {
        $data = Categories::all();
        return response()->json($data)->
        header("Content-Type", "application/json; charset=utf8");
    }

    public function create (Request $request)
    {
        $input = $request->all();
        $image = $request->file("image");

        $manager = new ImageManager(new Driver());
        $imageName = uniqid().".webp";
        $sizes = [50,150,300,600,1200];

        foreach ($sizes as $size) {
            $imageSave = $manager->read($image);
            $imageSave->scale(width: $size);
            $path = public_path("upload/".$size."_" .$imageName);
            $imageSave->toWebp()->save($path);
        }
        $input["image"]=$imageName;
        $category = Categories::create($input);

        return response()->json("end ",201,
            ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'],
            JSON_UNESCAPED_UNICODE);
    }

    public function delete($id)
    {
        $category = Categories::find($id);

        if ($category) {
            $sizes = [50,150,300,600,1200];
            foreach ($sizes as $size) {
                $path = public_path("upload/".$size."_".$category->image);
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $category->delete();

            return response()->json('Category deleted successfully', 200);
        } else {
            return response()->json('Category not found', 404);
        }
    }

    public function update(Request $request, $id)
{
    $category = Categories::find($id);
    
    if (!$category) {
        return response()->json('Category not found', 404);
    }

    $input = $request->all();

    if ($request->hasFile('image')) {
        $sizes = [50,150,300,600,1200];
        foreach ($sizes as $size) {
            $oldPath = public_path("upload/".$size."_".$category->image);
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        $image = $request->file("image");
        $manager = new ImageManager(new Driver());
        $imageName = uniqid().".webp";
        
        foreach ($sizes as $size) {
            $imageSave = $manager->read($image);
            $imageSave->scale(width: $size);
            $path = public_path("upload/".$size."_" .$imageName);
            $imageSave->toWebp()->save($path);
        }

        $input["image"] = $imageName;
    }

    $category->update($input);

    return response()->json('Category updated successfully', 200);
}

}
