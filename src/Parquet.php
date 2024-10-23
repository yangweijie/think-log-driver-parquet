<?php

declare (strict_types = 1);

namespace think\log\driver;

use Flow\Parquet\ParquetFile\Schema;
use Flow\Parquet\ParquetFile\Schema\FlatColumn;
use Flow\Parquet\Reader;
use Flow\Parquet\Writer;
use think\App;
use think\contract\LogHandlerInterface;

use function Flow\ETL\Adapter\Parquet\to_parquet;
use function Flow\ETL\Adapter\Parquet\from_parquet;
use function Flow\ETL\DSL\{data_frame, from_array, to_array, overwrite};
/**
 * 本地化调试输出到文件
 */
class Parquet implements LogHandlerInterface
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        'time_format'  => 'Y-m-d H:i:s.v',
        'single'       => false,
        'file_size'    => 2097152,
        'path'         => '',
        'apart_level'  => [],
        'max_files'    => 0,
        'json'         => false,
        'json_options' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        'format'       => '[%s][%s] %s',
    ];

    // 实例化并传入参数
    public function __construct(App $app, $config = [])
    {
        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }

        if (empty($this->config['format'])) {
            $this->config['format'] = '[%s][%s] %s';
        }

        if (empty($this->config['path'])) {
            $this->config['path'] = $app->getRuntimePath() . 'log';
        }

        if (substr($this->config['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->config['path'] .= DIRECTORY_SEPARATOR;
        }
    }

    public function getTraceId(){
        return '';
    }

    /**
     * 日志写入接口
     * @access public
     * @param array $log 日志信息
     * @return bool
     */
    public function save(array $log): bool
    {
        $destination = $this->getMasterLogFile();
        $path = dirname($destination);
        dump($destination);
        dump($path);
        dump(is_dir($path));
        !is_dir($path) && mkdir($path, 0755, true);

        $info = [];

        // 日志信息封装
        $time = \DateTime::createFromFormat('U.u', sprintf('%f', microtime(true)))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $created_at = $time->getTimestamp();
        $datetime = $time->format($this->config['time_format']);

        foreach ($log as $type => $val) {
            $message = [];
            foreach ($val as $msg) {
                $message[] = ['created_at'=>$created_at, 'create_at' => $datetime, 'type' => $type, 'msg' => $msg, 'trace_id'=>$this->getTraceId()];
            }

            if (true === $this->config['apart_level'] || in_array($type, $this->config['apart_level'])) {
                // 独立记录的日志级别
                $filename = $this->getApartLevelFile($path, $type);
                $this->write($message, $filename);
                continue;
            }

            $info[$type] = $message;
        }

        if ($info) {
            return $this->write($info, $destination);
        }

        return true;
    }

    public function getSchema()
    {
        $schema = Schema::with(
            FlatColumn::int64('id'),
            FlatColumn::string('trace_id'),
            FlatColumn::string('type'),
            FlatColumn::int64('created_at'),
            FlatColumn::string('create_at')
        );
        return $schema;
    }

    /**
     * 日志写入
     * @access protected
     * @param array  $message     日志信息
     * @param string $destination 日志文件
     * @return bool
     */
    protected function write(array $message, string $destination): bool
    {

//        $info = [];
//        foreach ($message as $type => $msg) {
//            foreach ($msg as $m) {
//                $info[] = json_encode($m, JSON_UNESCAPED_UNICODE);
//            }
//        }
//
//        $message = implode(PHP_EOL, $info) . PHP_EOL;
//
//        return error_log($message, 3, $destination);
//
        $info = [];

        foreach ($message as $type => $msges) {
            foreach ($msges as $msg) {
                $info[] = $msg;
            }
        }

        $old_logs = [];
        if(is_file($destination)){
            data_frame()
                ->read(from_parquet(
                    $destination,
                ))
                ->collect()
                ->write(to_array($old_logs))
                ->run();
        }
        data_frame()
            ->read(from_array(array_merge($old_logs, $info)))
            ->collect()
            ->saveMode(overwrite())
            ->write(to_parquet($destination))
            ->run();

        return true;

    }

    /**
     * 获取主日志文件名
     * @access public
     * @return string
     */
    protected function getMasterLogFile(): string
    {
        $filename = date('Ymd') . '.parquet';
        $destination = $this->config['path'] . $filename;
        return $destination;
    }

    /**
     * 获取独立日志文件名
     * @access public
     * @param string $path 日志目录
     * @param string $type 日志类型
     * @return string
     */
    protected function getApartLevelFile(string $path, string $type): string
    {

        if ($this->config['single']) {
            $name = is_string($this->config['single']) ? $this->config['single'] : 'single';

            $name .= '_' . $type;
        } elseif ($this->config['max_files']) {
            $name = date('Ymd') . '_' . $type;
        } else {
            $name = date('d') . '_' . $type;
        }

        return $path . DIRECTORY_SEPARATOR . $name . '.parquet';
    }

    /**
     * 检查日志文件大小并自动生成备份文件
     * @access protected
     * @param string $destination 日志文件
     * @return void
     */
    protected function checkLogSize(string $destination): void
    {
        if (is_file($destination) && floor($this->config['file_size']) <= filesize($destination)) {
            try {
                rename($destination, dirname($destination) . DIRECTORY_SEPARATOR . basename($destination, '.log') . '-' . time() . '.log');
            } catch (\Exception $e) {
                //
            }
        }
    }
}
