<?php
declare(strict_types=1);

/**
 * JiumiAdmin is committed to providing solutions for quickly building web applications
 * Please view the LICENSE file that was distributed with this source code,
 * For the full copyright and license information.
 * Thank you very much for using JiumiAdmin.
 *
 * @Author X.Mo<root@imoi.cn>
 * @Link   https://gitee.com/jiumikeji/JiumiAdmin
 */

namespace Jiumi\Office;

interface ExcelPropertyInterface
{
    public function import(\Jiumi\JiumiModel $model, ?\Closure $closure = null): bool;

    public function export(string $filename, array|\Closure $closure): \Psr\Http\Message\ResponseInterface;
}