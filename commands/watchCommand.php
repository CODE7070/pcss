<?php
namespace PCSS\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Finder\Finder;

class watchCommand extends Command{
    private $allFile=array();
    private $outputDir='';


    protected function configure(){
        $this
        ->setName('watch')
        ->addOption('src',null,InputOption::VALUE_REQUIRED,'a file or a dir to be watched', 1)
        ->addOption('dest',null,InputOption::VALUE_REQUIRED,'output dir', 1)
        ->setDescription('watch something')
        ->setHelp('watch file or watch dir');
    }

    protected function execute(InputInterface $input, OutputInterface $output){
        $src=$input->getOption('src');
        $dest=$input->getOption('dest');

        $path=$this->isFileOrDir($src);
        if($path==false){
            $output->writeln('a valid file or dir');
            return ;
        }
        if($this->isFileOrDir($dest)==false){
            $output->writeln('a valid output dir');
            return ;
        }
		$this->outputDir=$dest;
        if($path==='file'){
            $this->watchFile($src);
        }else{
			$this->watchDir($src);
		}
    }

    /**
	 * 循环监视目录
	 * 
	 * @param string $dirname 监视的目录
	 * @param string $outputdir 默认为空，输出的目录
	 */
	public function watchDir($dirname){
		while(TRUE){
			$this->fetchDir($dirname);
			sleep(3);
		}
	}
    /**
	 * 循环监视文件
	 * 
	 * @param string $filename 监视的文件
	 * @param string $outputdir 默认为空，输出的目录
	 */
	public function watchFile($filename){
		while(TRUE){
			$this->fetchFile($filename);
			sleep(3);
		}
	}
    /**
	 * 监视目录
	 * 
	 * @param string $dirname 要被监视的目录名字
	 */
	private function fetchDir($dirname){
		$finder = new Finder();
		$finder->files()->in($dirname);

		foreach ($finder as $file) {
			$this->fetchFile($file->getRealPath());
		}
	}
    /**
	 * 监视文件
	 * 
	 * @param string $dirname 要被监视的目录名字
	 */
	private function fetchFile($filename){
		$ext=$this->get_extension($filename);

		if($ext=='php' ){

			$file_md5=$this->allFile[$filename];

			if(empty($file_md5)){
				//开启子进程执行php boot文件
                $this->fetchPHP($filename);
				//将pcss加入到监控中
				$pcss_file=dirname($filename).'\\'.basename($filename,'.php').'.pcss';
				if(!file_exists($pcss_file)){
    				echo "$pcss_file not found!\n";
    				return;
				}
				$this->allFile[$pcss_file]=md5_file($pcss_file);
				$this->allFile[$filename]=md5_file($filename);
				return;
			}
				
			if($file_md5!=md5_file($filename)){
				//开启子进程执行php boot文件
				$this->fetchPHP($filename);
				//将pcss加入到监控中
				$pcss_file=dirname($filename).'\\'.basename($filename,'.php').'.pcss';
				if(!file_exists($pcss_file)){
    				echo "$pcss_file not found!\n";
    				return;
				}
				$this->allFile[$pcss_file]=md5_file($pcss_file);
				$this->allFile[$filename]=md5_file($filename);
			}

		}else if($ext=='pcss'){
			
			$file_md5=$this->allFile[$filename];
			if(empty($file_md5)){
				//还没有监控
				return;
			}
			//监控后，文件发生改变
			var_dump($file_md5);
			if($file_md5!=md5_file($filename)){
				//执行对应的php文件
				$php_file=dirname($filename).'/'.basename($filename,'.pcss').'.php';
				var_dump($php_file);
				$this->fetchPHP($php_file);
				$this->allFile[$php_file]=md5_file($php_file);
				$this->allFile[$filename]=md5_file($filename);
			}
		}
	}
    private function fetchPHP($phpFile){
		global $rootPath;
        $bootFile=$rootPath.'\class\boot.php';
		$shutdownFile=$rootPath.'\class\shutdown.php';
		$includeFile="include('$bootFile');include('$phpFile');include('$shutdownFile');";
		$path="\$destPath='{$this->outputDir}';\$currenFile='$phpFile';";
		$cmd=$path.$includeFile;

        exec('php -r "'.$cmd .'"',$output);
        for($i=0;$i<count($output);$i++){
            echo $output[$i] ."\r\n";
        }
    }

    private function isFileOrDir($path){
        if(is_dir($path)){
			return 'dir';
		}else if(is_file($path)){
			return 'file';
		}else{
			return false;
		}
    }
    /**
	 * 获取某个文件的后缀名
	 * 
	 * @param string $file 要取得后缀名的文件
	 * @return string 返回后缀名
	 */
	private function get_extension($file)
	{
		$info = pathinfo($file);
		if(empty($info['extension'])){
			return '';
		}
		return $info['extension'];
	}
}