<?php
declare(strict_types=1);

/**
 * @Date：2023/5/22 15:45
 * @Author LBG
 * @Link   http://www.hebei9.cn
 * @Copyright：Copyright (c) 2022 - 2035, 河北九米电子科技有限公司, Inc.
 */

namespace Jiumi\Office;

interface ExcelPropertyInterface
{
    public function import(\Jiumi\JiumiModel $model, ?\Closure $closure = null): bool;

    public function export(string $filename, array|\Closure $closure): \Psr\Http\Message\ResponseInterface;
}