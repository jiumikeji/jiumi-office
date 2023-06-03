<?php
declare(strict_types=1);

/**
 * @Date：2023/5/22 15:45
 * @Author LBG
 * @Link   http://www.hebei9.cn
 * @Copyright：Copyright (c) 2022 - 2035, 河北九米电子科技有限公司, Inc.
 */

namespace Jiumi\Office\Excel;

use Jiumi\Exception\JiumiException;
use Jiumi\Office\ExcelPropertyInterface;
use Jiumi\Office\JiumiExcel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;

class PhpOffice extends JiumiExcel implements ExcelPropertyInterface
{

    /**
     * 导入
     * @param \Jiumi\JiumiModel $model
     * @param \Closure|null $closure
     * @return bool
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function import(\Jiumi\JiumiModel $model, ?\Closure $closure = null): bool
    {
        $request = container()->get(\Jiumi\JiumiRequest::class);
        $data = [];
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $tempFileName = 'import_'.time().'.'.$file->getExtension();
            $tempFilePath = BASE_PATH . '/runtime/'. $tempFileName;
            file_put_contents($tempFilePath, $file->getStream()->getContents());
            $reader = IOFactory::createReader(IOFactory::identify($tempFilePath));
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($tempFilePath);
            $endCell = isset($this->property) ? $this->getColumnIndex(count($this->property)) : null;
            try {
                foreach ($sheet->getActiveSheet()->getRowIterator(2) as $row) {
                    $temp = [];
                    foreach ($row->getCellIterator('A', $endCell) as $index => $item) {
                        $propertyIndex = ord($index) - 65;
                        if (isset($this->property[$propertyIndex])) {
                            $temp[$this->property[$propertyIndex]['name']] = $item->getFormattedValue();
                        }
                    }
                    if (! empty($temp)) {
                        $data[] = $temp;
                    }
                }
                unlink($tempFilePath);
            } catch (\Throwable $e) {
                unlink($tempFilePath);
                throw new JiumiException($e->getMessage());
            }
        } else {
            return false;
        }
        if ($closure instanceof \Closure) {
            return $closure($model, $data);
        }

        foreach ($data as $datum) {
            $model::create($datum);
        }
        return true;
    }

    /**
     * 导出
     * @param string $filename
     * @param array|\Closure $closure
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function export(string $filename, array|\Closure $closure, \Closure $callbackData = null): \Psr\Http\Message\ResponseInterface
    {
        $spread = new Spreadsheet();
        $sheet  = $spread->getActiveSheet();
        $filename .= '.xlsx';

        is_array($closure) ? $data = &$closure : $data = $closure();

        // 表头
        $titleStart = 0;
        foreach ($this->property as $item) {
            $headerColumn = $this->getColumnIndex($titleStart) . '1';
            $sheet->setCellValue($headerColumn, $item['value']);
            $style = $sheet->getStyle($headerColumn)->getFont()->setBold(true);
            $columnDimension = $sheet->getColumnDimension($headerColumn[0]);

            empty($item['width']) ? $columnDimension->setAutoSize(true) : $columnDimension->setWidth((float) $item['width']);

            empty($item['align']) || $sheet->getStyle($headerColumn)->getAlignment()->setHorizontal($item['align']);

            empty($item['headColor']) || $style->setColor(new Color(str_replace('#', '', $item['headColor'])));

            if (!empty($item['headBgColor'])) {
                $sheet->getStyle($headerColumn)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB(str_replace('#', '', $item['headBgColor']));
            }
            $titleStart++;
        }

        $generate = $this->yieldExcelData($data);

        // 表体
        try {
            $row = 2;
            while ($generate->valid()) {
                $column = 0;
                $items = $generate->current();
                foreach ($items as $name => $value) {
                    $columnRow = $this->getColumnIndex($column) . $row;
                    $annotation = '';
                    foreach ($this->property as $item) {
                        if ($item['name'] == $name) {
                            $annotation = $item;
                            break;
                        }
                    }

                    if (!empty($annotation['dictName'])) {
                        $sheet->setCellValue($columnRow, $annotation['dictName'][$value]);
                    } else if (!empty($annotation['path'])){
                        $sheet->setCellValue($columnRow, data_get($items, $annotation['path']));
                    } else if (!empty($annotation['dictData'])) {
                        $sheet->setCellValue($columnRow, $annotation['dictData'][$value]);
                    } else if(!empty($this->dictData[$name])){
                        $sheet->setCellValue($columnRow, $this->dictData[$name][$value] ?? '');
                    } else {
                        $sheet->setCellValue($columnRow, $value . "\t");
                    }

                    if (! empty($item['color'])) {
                        $sheet->getStyle($columnRow)->getFont()
                            ->setColor(new Color(str_replace('#', '', $annotation['color'])));
                    }

                    if (! empty($item['bgColor'])) {
                        $sheet->getStyle($columnRow)->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB(str_replace('#', '', $annotation['bgColor']));
                    }
                    $column++;
                }
                $generate->next();
                $row++;
            }
        } catch (\RuntimeException $e) {}

        $writer = IOFactory::createWriter($spread, 'Xlsx');
        ob_start();
        $writer->save('php://output');
        $res = $this->downloadExcel($filename, ob_get_contents());
        ob_end_clean();
        $spread->disconnectWorksheets();

        return $res;
    }

    protected function yieldExcelData(array &$data): \Generator
    {
        foreach ($data as $dat) {
            $yield = [];
            foreach ($this->property as $item) {
                $yield[ $item['name'] ] = $dat[$item['name']] ?? '';
            }
            yield $yield;
        }
    }
}