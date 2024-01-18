<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     tags={"Product"},
     *     path="/api/products",
     *     @OA\Response(response="200", description="List Products.")
     * )
     */
    public function getList()
    {
//        $data = Product::all();
        $data = Product::with('category')->get();
        return response()->json($data)
            ->header('Content-Type', 'application/json; charset=utf-8');
    }


    /**
     * @OA\Get(
     *     tags={"Product"},
     *     path="/api/products/{id}",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Ідентифікатор продукту",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(response="200", description="Отримати конкретний продукт за його ідентифікатором."),
     *     @OA\Response(response="404", description="Продукт не знайдено.")
     * )
     */
    public function getById($id)
    {
        $product = Product::with('category')->find($id);

        if (!$product) {
            return response()->json(['error' => 'Продукт не знайдено'], 404)
                ->header('Content-Type', 'application/json; charset=utf-8');
        }

        return response()->json($product)
            ->header('Content-Type', 'application/json; charset=utf-8');
    }



    /**
     * @OA\Post(
     *     tags={"Product"},
     *     path="/api/product",
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"category_id","name","price","quantity","description","images[]"},
     *                 @OA\Property(
     *                     property="category_id",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="name",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="price",
     *                     type="number"
     *                 ),
     *                 @OA\Property(
     *                      property="quantity",
     *                      type="number"
     *                  ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="images[]",
     *                     type="array",
     *                     @OA\Items(type="string", format="binary")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response="200", description="Add Product.")
     * )
     */
    public function store(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required',
            'price' => 'required',
            'description' => 'required',
            'quantity'=>'required',
            'images' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }
        $images = $request->file('images');
        $product = Product::create($input);
        $sizes = [50,150,300,600,1200];
        // create image manager with desired driver
        $manager = new ImageManager(new Driver());
        if ($request->hasFile('images')) {
            foreach ($images as $image) {
                $imageName = uniqid() . '.webp';
                foreach ($sizes as $size) {
                    $fileSave = $size."_".$imageName;
                    $imageRead = $manager->read($image);
                    $imageRead->scale(width: $size);
                    $path=public_path('upload/'.$fileSave);
                    $imageRead->toWebp()->save($path);
                }

                ProductImage::create([
                    'product_id' => $product->id,
                    'name' => $imageName
                ]);
            }
        }

        $product->load('product_images');

        return response()->json($product, 200, [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Charset' => 'utf-8'
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @OA\Get(
     *     tags={"Product"},
     *     path="/api/productimg",
     *     summary="Get photo names by product IDs",
     *     @OA\Parameter(
     *         name="ids[]",
     *         in="query",
     *         description="Array of product IDs",
     *         required=true,
     *         @OA\Schema(type="array", @OA\Items(type="integer"), example={1, 2, 3})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="photo_names", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *     )
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
 

 public function getPhotoNamesByIds(Request $request)
 {
     $ids = $request->all();
 
     if (isset($ids['ids']) && is_array($ids['ids'])) {
         $ids['ids'] = array_filter($ids['ids']);
         $ids['ids'] = array_map('intval', $ids['ids']);
 
         $photoNames = [];
 
         foreach ($ids['ids'] as $id) {
             $result = ProductImage::where('product_id', $id)->get(['name', 'product_id'])->toArray();
 
             if (!empty($result)) {
                 $photoNames[$id] = $result;
             }
         }
 
         return response()->json(['photo_names' => $photoNames]);
     } else {
         return response()->json(['message' => 'Не знайдено ключ "ids" у запиті або він не є масивом.']);
     }
 }
 

 /**
 * @OA\Get(
 *     tags={"Product"},
 *     path="/api/productimg/{id}",
 *     summary="Get photo names by product ID",
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         description="Product ID",
 *         required=true,
 *         @OA\Schema(type="integer", example=1)
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="photo_names", type="array", @OA\Items(type="object", @OA\Property(property="name", type="string"), @OA\Property(property="product_id", type="integer")))
 *         )
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Product not found",
 *     )
 * )
 */
public function getPhotoNamesById($id)
{
    $result = ProductImage::where('product_id', $id)->get(['name', 'product_id'])->toArray();

    if (!empty($result)) {
        return response()->json(['photo_names' => $result]);
    } else {
        return response()->json(['message' => 'Product not found'], 404);
    }
}


}