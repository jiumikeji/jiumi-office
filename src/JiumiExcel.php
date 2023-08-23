<?php
declare(strict_types=1);

/**
 * @Date：2023/5/22 15:45
 * @Author LBG
 * @Link   http://www.hebei9.cn
 * @Copyright：Copyright (c) 2022 - 2035, 河北九米电子科技有限公司, Inc.
 */

namespace Jiumi\Office;

use App\System\Service\SystemDictDataService;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Jiumi\Exception\JiumiException;
use Jiumi\Interfaces\JiumiModelExcel;
use Jiumi\JiumiResponse;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

abstract class JiumiExcel
{
    public const ANNOTATION_NAME = 'Jiumi\Annotation\ExcelProperty';

    /**
     * @var array|null
     */
    protected ?array $annotationMate;

    /**
     * @var array
     */
    protected array $property = [];
    protected array $dictData = [];


    /**
     * @param String $dto
     * @param JiumiModel $model
     */
    public function __construct(string $dto)
    {
        if (!(new $dto) instanceof JiumiModelExcel) {
            throw new JiumiException('dto does not implement an interface of the JiumiModelExcel', 500);
        }
        $dtoObject = new $dto();
        if (method_exists($dtoObject, 'dictData')) {
            $this->dictData = $dtoObject->dictData();
        }
        $this->annotationMate = AnnotationCollector::get($dto);
        $this->parseProperty();
    }

    /**
     * @return array
     */
    public function getProperty(): array
    {
        return $this->property;
    }

    /**
     * @return array
     */
    public function getAnnotationInfo(): array
    {
        return $this->annotationMate;
    }

    /**
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \RedisException
     */
    protected function parseProperty(): void
    {
        if (empty($this->annotationMate) || !isset($this->annotationMate['_c'])) {
            throw new JiumiException('dto annotation info is empty', 500);
        }

        foreach ($this->annotationMate['_p'] as $name => $mate) {
            $this->property[$mate[self::ANNOTATION_NAME]->index] = [
                'name' => $name,
                'value' => $mate[self::ANNOTATION_NAME]->value,
                'width' => $mate[self::ANNOTATION_NAME]->width ?? null,
                'align' => $mate[self::ANNOTATION_NAME]->align ?? null,
                'headColor' => $mate[self::ANNOTATION_NAME]->headColor ?? null,
                'headBgColor' => $mate[self::ANNOTATION_NAME]->headBgColor ?? null,
                'color' => $mate[self::ANNOTATION_NAME]->color ?? null,
                'bgColor' => $mate[self::ANNOTATION_NAME]->bgColor ?? null,
                'dictData' => $mate[self::ANNOTATION_NAME]->dictData,
                'dictName' => empty($mate[self::ANNOTATION_NAME]->dictName) ? null : $this->getDictData($mate[self::ANNOTATION_NAME]->dictName),
                'path' => $mate[self::ANNOTATION_NAME]->path ?? null,
            ];
        }
        ksort($this->property);
    }

    /**
     * 下载excel
     * @param string $filename
     * @param string $content
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function downloadExcel(string $filename, string $content): \Psr\Http\Message\ResponseInterface
    {
        return container()->get(JiumiResponse::class)->getResponse()
            ->withHeader('Server', 'JiumiAdmin')
            ->withHeader('content-description', 'File Transfer')
            ->withHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->withHeader('content-disposition', "attachment; filename={$filename}; filename*=UTF-8''" . rawurlencode($filename))
            ->withHeader('content-transfer-encoding', 'binary')
            ->withHeader('pragma', 'public')
            ->withBody(new SwooleStream($content));
    }

    /**
     * 获取字典数据
     * @param string $dictName
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \RedisException
     */
    protected function getDictData(string $dictName): array
    {
        $data = [];
        foreach (container()->get(SystemDictDataService::class)->getList(['code' => $dictName]) as $item) {
            $data[$item['key']] = $item['title'];
        }

        return $data;
    }

    /**
     * 获取 excel 列索引
     * @param int $columnIndex
     * @return string
     */
    protected function getColumnIndex(int $columnIndex = 0): string
    {
        if ($columnIndex < 26) {
            return chr(65 + $columnIndex);
        } else if ($columnIndex < 702) {
            return chr(64 + intval($columnIndex / 26)) . chr(65 + $columnIndex % 26);
        } else {
            return chr(64 + intval(($columnIndex - 26) / 676)) . chr(65 + intval((($columnIndex - 26) % 676) / 26)) . chr(65 + $columnIndex % 26);
        }
    }
}
