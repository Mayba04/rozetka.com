<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Categories;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{

    /**
     * @OA\Get(
     *     tags={"Category"},
     *     path="/api/categories",
     *     @OA\Response(response="200", description="List Categories.")
     * )
     */

    public function getList()
    {
        $data = Categories::all();
        return response()->json($data)->
        header("Content-Type", "application/json; charset=utf8");
    }

    /**
     * @OA\Get(
     *     tags={"Category"},
     *     path="/api/categories/{id}",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(
     *             type="integer"
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Get Category by ID."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Category not found."
     *     )
     * )
     */
    public function getId($id)
    {
        $category = Categories::find($id);
        if ($category) {
            return response()->json($category)
                ->header("Content-Type", "application/json; charset=utf8");
        } else {
            return response()->json(['message' => 'Category not found'], 404)
                ->header("Content-Type", "application/json; charset=utf8");
        }
    }

    /**
     * @OA\Post(
     *     tags={"Category"},
     *     path="/api/categories",
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name","image"},
     *                 @OA\Property(
     *                     property="image",
     *                     type="file",
     *                ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response="200", description="Add Category.")
     * )
     */

    public function create (Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->where(function ($query) use ($request) {
                    return $query->where('name', $request->name);
                }),
            ],
            'image' => 'required|image|max:10240', // 10MB
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        $image = $request->file("image");
        $imageName = uniqid() . ".webp";
        $sizes = [50, 150, 300, 600, 1200];
        $manager = new ImageManager(new Driver());
    
        foreach ($sizes as $size) {
            $imageSave = $manager->read($image);
            $imageSave->scale(width: $size);
            $path = public_path("upload/" . $size . "_" . $imageName);
            $imageSave->toWebp()->save($path);
        }
    
        $category = Categories::create([
            'name' => $request->name,
            'image' => $imageName,
        ]);
    
        return response()->json("end ",201,
            ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'],
            JSON_UNESCAPED_UNICODE);
    }

    /**
     * @OA\Delete(
     *     path="/api/categories/{id}",
     *     tags={"Category"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ідентифікатор категорії",
     *         required=true,
     *         @OA\Schema(
     *             type="number",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Успішне видалення категорії"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Категорії не знайдено"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Не авторизований"
     *     )
     * )
     */

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

    /**
     * @OA\Post(
     *     tags={"Category"},
     *     path="/api/categories/edit/{id}",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ідентифікатор категорії",
     *         required=true,
     *         @OA\Schema(
     *             type="number",
     *             format="int64"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="image",
     *                     type="file"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response="200", description="Add Category.")
     * )
     */
    
    public function edit($id, Request $request) 
    {
        $category = Categories::findOrFail($id);
        $imageName = $category->image;

        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->ignore($category->id),
            ],
            'image' => 'sometimes|image|max:10240', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->hasFile("image")) {
            foreach ([50, 150, 300, 600, 1200] as $size) {
                $oldPath = public_path("upload/" . $size . "_" . $imageName);
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $image = $request->file("image");
            $manager = new ImageManager(new Driver());
            $imageName = uniqid() . ".webp";
            
            foreach ([50, 150, 300, 600, 1200] as $size) {
                $imageSave = $manager->read($image);
                $imageSave->scale(width: $size);
                $path = public_path("upload/" . $size . "_" . $imageName);
                $imageSave->toWebp()->save($path);
            }

            $inputs['image'] = $imageName;
        }

        $category->update($inputs);
        return response()->json($category,200,
            ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'], JSON_UNESCAPED_UNICODE);
    }

}
