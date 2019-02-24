<?php

namespace devgroup\dropzone;

use app\components\Imager;
use Yii;
use yii\base\Action;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\HttpException;
use yii\web\UploadedFile;

class UploadAction extends Action
{
    const RENAME_UNIQUE = 'unique';
    const RENAME_UNIQUE_WITH_EXTENSION = 'uniquewithext';
    const RENAME_NONE_OVERWRITE = 'overwrite';

    public $model = false;
    public $modelName = false;
    public $fileName = 'file';
    public $upload = 'uploads/temp';

    public $afterUploadHandler = null;
    public $afterUploadData = null;
    public $rename = false; // false|unique|uniquewithext

    protected $uploadDir = '';
    protected $uploadSrc = '';

    public function init()
    {
        parent::init();

        $this->uploadDir = Yii::getAlias('@webroot/' . $this->upload . '/');
        $this->uploadSrc = Yii::getAlias('@web/' . $this->upload . '/');

        if (!is_dir($this->uploadDir))
            mkdir($this->uploadDir, 0777, true);
    }

    public function setUpload($upload)
    {
        $this->upload = $upload;

        $this->uploadDir = Yii::getAlias('@webroot/' . $this->upload . '/');
        $this->uploadSrc = Yii::getAlias('@web/' . $this->upload . '/');

        if (!is_dir($this->uploadDir))
            mkdir($this->uploadDir, 0777, true);
    }

    public function run()
    {
        $validFlag = true;
        if ($this->modelName)
            $this->fileName = isset($_FILES[$this->modelName]) ? Html::getInputName($this->model, key($_FILES[$this->modelName]['name'])) : '';
        $file = UploadedFile::getInstanceByName($this->fileName);
        if ($file->hasError) {
            throw new HttpException(500, 'Upload error');
        }

        $fileName = false;
        if (!$this->rename || $this->rename == self::RENAME_NONE_OVERWRITE) {
            $fileName = $file->name;
            if ($this->rename != self::RENAME_NONE_OVERWRITE && file_exists($this->uploadDir . $fileName)) {
                $fileName = $file->baseName . '-' . uniqid() . '.' . $file->extension;
            }
        } else if ($this->rename == self::RENAME_UNIQUE_WITH_EXTENSION)
            $fileName = md5(time() . uniqid()) . '.' . $file->extension;
        else if ($this->rename == self::RENAME_UNIQUE)
            $fileName = md5(time() . uniqid());

        // validation
        $msg = '';
        if ($this->validateOptions) {
            if (isset($this->validateOptions['dimensions'])) {
                $minW = isset($this->validateOptions['dimensions']['minWidth']) ? $this->validateOptions['dimensions']['minWidth'] : null;
                $maxW = isset($this->validateOptions['dimensions']['maxWidth']) ? $this->validateOptions['dimensions']['maxWidth'] : null;
                $minH = isset($this->validateOptions['dimensions']['minHeight']) ? $this->validateOptions['dimensions']['minHeight'] : null;
                $maxH = isset($this->validateOptions['dimensions']['maxHeight']) ? $this->validateOptions['dimensions']['maxHeight'] : null;

                $imager = new Imager();
                $imageInfo = $imager->getImageInfo($file['tmp_name']);
                if ($minW && $imageInfo['width'] < $minW) {
                    $msg .= 'عرض تصویر نباید کوچکتر از ' . $minW . ' پیکسل باشد.<br>';
                    $validFlag = false;
                }
                if ($maxW && $imageInfo['width'] > $maxW) {
                    $msg .= 'عرض تصویر نباید بزرگتر از ' . $maxW . ' پیکسل باشد.<br>';
                    $validFlag = false;
                }
                if ($minH && $imageInfo['height'] < $minH) {
                    $msg .= 'ارتفاع تصویر نباید کوچکتر از ' . $minH . ' پیکسل باشد.<br>';
                    $validFlag = false;
                }
                if ($maxH && $imageInfo['height'] > $maxH) {
                    $msg .= 'عرض تصویر نباید بزرگتر از ' . $maxH . ' پیکسل باشد.<br>';
                    $validFlag = false;
                }
            }
            if (isset($this->validateOptions['acceptedTypes']) && is_array($this->validateOptions['acceptedTypes'])) {
                if (!in_array($ext, $this->validateOptions['acceptedTypes'])) {
                    $msg .= 'فرمت فایل مجاز نیست.<br>فرمت های مجاز: ' . implode(',', $this->validateOptions['acceptedTypes']) . '<br>';
                    $validFlag = false;
                }
            }
        }
        if ($validFlag) {
            if ($fileName && $file->saveAs($this->uploadDir . $fileName)) {
                $response = ['status' => true, 'filename' => $this->rename == self::RENAME_UNIQUE ? $fileName . '.' . $file->extension : $fileName];

                if (isset($this->afterUploadHandler)) {
                    $data = [
                        'data' => $this->afterUploadData,
                        'file' => $file,
                        'dirName' => $this->uploadDir,
                        'src' => $this->uploadSrc,
                        'filename' => $fileName,
                        'params' => Yii::$app->request->post(),
                    ];

                    if ($result = call_user_func($this->afterUploadHandler, $data)) {
                        $response['afterUpload'] = $result;
                    }
                }
            } else
                $response = ['status' => false, 'message' => 'در عملیات آپلود فایل خطایی رخ داده است.'];
        } else
            $response = ['status' => false, 'message' => $msg];
        return Json::encode($response);
    }
}