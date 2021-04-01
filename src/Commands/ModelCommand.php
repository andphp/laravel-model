<?php

namespace AndPHP\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Support\Facades\DB;

class ModelCommand extends Command
{
    protected $files;

    protected $type = 'AndphpModel';

    /**
     * The name and signature of the console command. 控制台命令的名称和签名
     *
     * @var string
     */
    protected $signature = 'andphp:model {name} {--table=} {--extend=}';

    /**
     * The console command description. 控制台命令描述
     *
     * @var string
     */
    protected $description = 'Create a new apaModel class';

    /**
     * Create a new command instance. 创建一个新的命令实例
     *
     * @return void
     */
    public function __construct()
    {

        parent::__construct();

        $this->files = new Filesystem();
    }

    /**
     * Execute the console command. 执行控制台命令
     * @return bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        $name = $this->qualifyClass($this->getNameInput());

        $path = $this->getPath($name);

        if ((!$this->hasOption('force') ||
                !$this->option('force')) &&
            $this->alreadyExists($this->getNameInput())) {
            $this->error($this->type . ' already exists!');

            return false;
        }

        // Next, we will generate the path to the location where this class' file should get
        // written. Then, we will build the class and make the proper replacements on the
        // stub files so that it gets the correctly formatted namespace and class name.
        $this->makeDirectory($path);

        $this->files->put($path, $this->buildClass($name));

        $this->info($this->type . ' created successfully.');
    }

    /**
     * Get the stub file for the generator. 获取生成器模板文件
     *
     * @return string
     */
    protected function getStub()
    {
        $stub = $stub ?? '/Stubs/model.plain.stub';

        return __DIR__ . $stub;
    }

    /**
     * Get the default namespace for the class. 获取该类的默认名称空间
     *
     * @param string $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\Models';
    }

    /**
     * * Build the class with the given name. 使用给定的名称构建类
     *
     * Remove the base controller import if we are already in base namespace. 如果我们已经在基命名空间中，则删除基控制器导入
     *
     * @param $name
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name)
    {
        $controllerNamespace = $this->getNamespace($name);

        $replace = [];

        $replace["use {$controllerNamespace}\Model;\n"] = '';

        return str_replace(
            array_keys($replace), array_values($replace), $this->buildClassParent($name)
        );
    }

    /**
     * Build the class with the given name. 使用给定的名称构建类
     *
     * @param $name
     * @return mixed
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClassParent($name)
    {
        $stub = $this->files->get($this->getStub());

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name)->replaceTable($stub, $name)
            ->replaceComments($stub, $name)->replaceFields($stub, $name)
            ->replaceExtendClass($stub, $this->option('extend'));
    }

    /**
     * Get the console command options. 获取控制台命令选项
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            [
                'extend',
                'i',
                InputOption::VALUE_NONE,
                'Generate a resource controller class.'
            ],
        ];
    }

    /**
     * Parse the class name and format according to the root namespace.  根据根命名空间解析类名和格式
     *
     * @param string $name
     * @return string
     */
    protected function qualifyClass($name)
    {
        $name = ltrim($name, '\\/');

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        $name = str_replace('/', '\\', $name);

        return $this->qualifyClass(
            $this->getDefaultNamespace(trim($rootNamespace, '\\')) . '\\' . $name
        );
    }

    /**
     * Determine if the class already exists.  确定类是否已经存在
     *
     * @param string $rawName
     * @return bool
     */
    protected function alreadyExists($rawName)
    {
        return $this->files->exists($this->getPath($this->qualifyClass($rawName)));
    }

    /**
     * Get the destination class path. 获取目标类路径
     *
     * @param string $name
     * @return string
     */
    protected function getPath($name)
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);

        return $this->laravel['path'] . '/' . str_replace('\\', '/', $name) . '.php';
    }

    /**
     * Build the directory for the class if necessary. 如果需要，为类构建目录
     *
     * @param string $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }

        return $path;
    }

    /**
     * Replace the namespace for the given stub. Replace the namespace for the given stub
     *
     * @param string $stub
     * @param string $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        $stub = str_replace(
            [
                'DummyNamespace',
                'DummyRootNamespace'
            ],
            [
                $this->getNamespace($name),
                $this->rootNamespace()
            ],
            $stub
        );

        return $this;
    }

    /**
     * Get the full namespace for a given class, without the class name. 获取给定类的完整名称空间，但不包含类名
     *
     * @param string $name
     * @return string
     */
    protected function getNamespace($name)
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    /**
     * Replace the class name for the given stub. 替换给定存根的类名
     *
     * @param string $stub
     * @param string $name
     * @return string
     */
    protected function replaceClass(&$stub, $name)
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);

        $stub = str_replace('DummyClass', $class, $stub);

        return $this;
    }

    /**
     * @param $stub
     * @param $name
     * @return $this
     */
    protected function replaceTable(&$stub, $name)
    {
        $stub = str_replace('DummyTable', $this->getTableName($name), $stub);

        return $this;
    }

    protected function replaceComments(&$stub, $name)
    {
        $tableName = $this->getTableName($name);
        $databaseName = $this->getDatabaseName();

        $sql = "SELECT
    CONCAT(
        ' * @property ',
				(case DATA_TYPE
						when 'varchar'  then 'string'
						when 'char'  then 'string'
						when 'char'  then 'string'
						when 'mediumint'  then 'int'
						when 'tinyint'  then 'int'
						when 'bigint'  then 'int'
						when 'timestamp'  then 'datetime'
						when 'decimal'  then 'float'
						else DATA_TYPE END)
        ,
        ' ',
        COLUMN_NAME, ' ', COLUMN_COMMENT
    ) as `comments`
FROM
    INFORMATION_SCHEMA. COLUMNS WHERE table_schema = '" . $databaseName . "' AND  table_name = '" . $tableName . "'";
        $CommentsArray = json_decode(json_encode(DB::select($sql)), true);
        $filterCommentsArray = array_column($CommentsArray, 'comments');
        $filterCommentsString = implode(PHP_EOL, $filterCommentsArray);
        $stub = str_replace('DummyComments', $filterCommentsString, $stub);
        return $this;
    }

    protected function replaceFields(&$stub, $name)
    {

        $tableName = $this->getTableName($name);
        $databaseName = $this->getDatabaseName();

        $sql = "SELECT concat('''',COLUMN_NAME,'''') as `fieldName` FROM INFORMATION_SCHEMA. COLUMNS WHERE table_schema = '" . $databaseName . "' and COLUMN_NAME <> 'id' AND  table_name = '" . $tableName . "'";
        $fieldsArray = json_decode(json_encode(DB::select($sql)), true);
        $filterFieldsArray = array_column($fieldsArray, 'fieldName');
        $filterFieldsString = implode(', ', $filterFieldsArray);
        $stub = str_replace('DummyFields', $filterFieldsString, $stub);
        return $this;
    }

    /**
     * 自定义继承 引用
     * @param $stub
     * @param $name
     * @return mixed
     */
    protected function replaceExtendClass($stub, $Extend)
    {
        $class = str_replace($this->getNamespace($Extend) . '\\', '', $Extend);

        $use = count(explode('\\', $Extend)) >= 2 ? "" : 'use ' . $this->qualifyClass($Extend) . "Model;";

        $stub = str_replace([
            'DummyUseNamespace',
            'DummyExtendClass'
        ], [
            $use,
            $class
        ], $stub);
        return $stub;
    }

    /**
     * Get the desired class name from the input. 从输入中获取所需的类名
     *
     * @return string
     */
    protected function getNameInput()
    {
        return trim($this->argument('name'));
    }

    /**
     * Get the root namespace for the class. 获取类的根名称空间
     *
     * @return string
     */
    protected function rootNamespace()
    {
        return $this->laravel->getNamespace();
    }


    /**
     * Get the console command arguments. 获取控制台命令参数
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            [
                'name',
                InputArgument::REQUIRED,
                'The name of the model'
            ],
        ];
    }

    protected function getTableNameInput()
    {
        return trim($this->option('table'));
    }

    protected function getTableName($name)
    {
        $tableNameFormClassName = str_replace($this->getNamespace($name) . '\\', '', $name) . 's';
        return self::toUnderScore((!empty($this->getTableNameInput())) ? $this->getTableNameInput() : $tableNameFormClassName);
    }

    protected function getDatabaseName()
    {
        return config('database.connections.mysql.database');
    }

    protected function toUnderScore($str)
    {
        $dstr = preg_replace_callback('/([A-Z]+)/', function ($matchs) {
            return '_' . strtolower($matchs[0]);
        }, $str);
        return trim(preg_replace('/_{2,}/', '_', $dstr), '_');
    }
}
