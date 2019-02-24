<?php

namespace devgroup\dropzone;

use app\models\Item;
use Yii;
use yii\base\Action;
use yii\db\ActiveRecord;
use yii\helpers\Json;

class RemoveAction extends Action
{
    const STORED_JSON_MODE = 'json';
    const STORED_FIELD_MODE = 'field';
    const STORED_RECORD_MODE = 'record';

    public $rename = false; // false|unique|uniquewithext

    public $tmpDir = 'uploads/temp';
    /**
     * @var string upload folder Dir
     */
    public $uploadDir;

    /**
     * @var array thumbnail sizes array
     */
    public $thumbSizes = array();
    /**
     * @var ActiveRecord model class instance
     */
    public $model;
    /**
     * @var string attribute name
     */
    public $attribute;
    /**
     * @var string saved in Database mode for this file field|record
     */
    public $storedMode;

    private function init()
    {
        if (!$this->uploadDir)
            throw new \Exception('{uploadDir} main files folder path is not specified.', 500);
        if (!$this->attribute)
            throw new \Exception('{attribute} attribute is not specified.', 500);
        if ($this->modelName && (empty($this->storedMode) || ($this->storedMode !== self::STORED_FIELD_MODE && $this->storedMode !== self::STORED_RECORD_MODE && $this->storedMode !== self::STORED_JSON_MODE)))
            throw new \Exception('{storedMode} stored mode in db is not specified. ("field" or "json" or "record")', 500);
    }

    public function run()
    {
        $this->init();
        $deleteFlag = false;
        $uploadDir = Yii::getAlias("@webroot/$this->uploadDir/");
        $tempDir = Yii::getAlias("@webroot/$this->tempDir/");
        if (isset($_POST['fileName'])) {
            $fileName = $_POST['fileName'];
            if ($this->rename == self::RENAME_UNIQUE)
                $fileName = pathinfo($fileName, PATHINFO_FILENAME);
            $file = new UploadedFiles($this->upload, $fileName);
            $deleteFlag = true;
            if ($this->model instanceof ActiveRecord) {
                $model = $this->model->find()->filterWhere([$this->attribute => pathinfo($fileName, PATHINFO_FILENAME)])->one();
                if ($model) {
                    if ($this->storedMode === self::STORED_FIELD_MODE) {
                        $model->{$this->attribute} = null;
                        $deleteFlag = $model->save(false) ? true : false;
                    } elseif ($this->storedMode === self::STORED_RECORD_MODE)
                        $deleteFlag = $model->delete() ? true : false;
                }
            }

            if ($deleteFlag) {
                if ($file->remove($fileName, true))
                    $response = ['status' => true, 'msg' => 'فایل با موفقیت حذف شد.'];
                else
                    $response = ['status' => false, 'msg' => 'حذف فایل با مشکل مواجه شد.'];
            } else
                $response = ['status' => false, 'msg' => 'حذف فایل با مشکل مواجه شد.'];
        } else
            $response = ['status' => false, 'message' => 'نام فایل موردنظر ارسال نشده است.'];
        return Json::encode($response);
    }
}