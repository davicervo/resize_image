<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\ImageManagerStatic as Image;

class ImageResizeController extends Controller
{
    protected $with;
    protected $height;
    protected $path;
    protected $quality;
    protected $formatImage;

    /**
     * @return mixed
     */
    public function getFormatImage()
    {
        return $this->formatImage;
    }

    /**
     * @param mixed $formatImage
     */
    public function setFormatImage($formatImage)
    {
        $this->formatImage = is_array($formatImage) ? $formatImage[1] : null;
    }

    /**
     * @return mixed
     */
    public function getWith()
    {
        return $this->with;
    }

    /**
     * @param mixed $with
     */
    public function setWith($with)
    {
        $this->with = $with;
    }

    /**
     * @return mixed
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @param mixed $height
     */
    public function setHeight($height)
    {
        $this->height = $height;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param mixed $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return mixed
     */
    public function getQuality()
    {
        return $this->quality;
    }

    /**
     * @param mixed $quality
     */
    public function setQuality($quality)
    {
        $this->quality = $quality;
    }


    public function helper()
    {
        $helper = [
            'params' => [
                "p" => 'Path -- Caminho onde a imagem está hospedada, não precisa informar o bucket.',
                "q" => 'Quality -- Qualidade em que a imagem será renderizada ( 10 ~ 100 )',
                "w" => 'Width -- Largura que a imagem será renderizada, se vazio utilizará a da própria imagem',
                "h" => 'Height -- Altura que a imagem será renderizada, se vazio utilizará a da própria imagem',
            ],
            'required' => [
                'p' => 'Path é obrigatório'
            ],
            'errors' => [
                422 => 'Invalid parameters you need to send the {p} parameter',
                404 => 'Invalid path url, image not found',
            ],
            'example' => [
                01 => env('APP_URL') . '/api/image_resize/p=public/images/como-o-seu-dna-pode-trazer-lucro-as-grandes-empresas.jpeg&w=500&h=350&q=80'
            ]
        ];

        return response()->json($helper);
    }

    public function show(Request $request, $path)
    {
        $pos = strpos($path, 'p=');
        if ($pos === false) {
            return response()->json([
                'code' => 422,
                'message' => 'Invalid parameters you need to send the {p}  parameter'
            ])->throwResponse();
        }

        $params = explode('&', $path);

        foreach ($params as $parm) {
            $items = explode('=', $parm);
            $query[strtolower($items[0])] = $items[1];
        }

        $this->validation($query);

        //criando imagem no s3
        $image = Image::make(getS3Image($this->getPath()))->fit($this->getWith(), $this->getHeight());

        return $image->response($this->getFormatImage(), $this->getQuality());
    }

    public function validation($query)
    {
        //tratando / no caminho da imagem
        $query['p'] = substr($query['p'], 0, 1) == '/' ? substr($query['p'], 1) : $query['p'];

        $file_headers = @get_headers(getS3Image($query['p']));
        if (!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
            return response()->json([
                'code' => 404,
                'message' => 'Invalid image url'
            ])->throwResponse();
        }

        if (!isset($query['w'])) {
            $query['w'] = Image::make(getS3Image($query['p']))->width();
        }

        if (!isset($query['h'])) {
            $query['h'] = Image::make(getS3Image($query['p']))->height();
        }

        if (!isset($query['q'])) {
            $query['q'] = 100;
        }

        if ($query['q'] > 100) {
            $query['q'] = 100;
        } elseif ($query['q'] <= 0) {
            $query['q'] = 10;
        }

        foreach ($query as $key => $value) {
            $response[$key] = is_numeric($value) ? (int) $value : $value;
        }

        return $this->hidrate($response);
    }

    public function hidrate(array $response)
    {
        $params = [
            "p" => NULL,
            "q" => NULL,
            "w" => NULL,
            "h" => NULL,
        ];

        $result = array_merge($params, $response);

        $this->setHeight($result['h']);
        $this->setWith($result['w']);
        $this->setPath($result['p']);
        $this->setQuality($result['q']);

        $info = Image::make(getS3Image($this->getPath()))->mime();
        $this->setFormatImage(null);
    }
}
