<?php
/**
 * 提取网页正文
 * 
 * 理论依据：基于网页正文是网页中文字分布最密集的这一原理
 * 
 * @author ChunYang.Jiang<ChunYang#staff.sina.com.cn>
 * @copyright sina
 * @version 0.8
 */
class Extract{
	//待提取网页html
	private $html;
	//网页html预处理切块
	private $block_array = array();
	//切块长度
	private $block_size = array();
	//提取的博文内容
	private $content = '';
	//提取的网页标题
	private $title = '';
	//h1-h6
	private $hn = array();
	//发布时间
	private $pubtime = '';
	//网页编码
	private $encode = '';
	//目标编码
	private $outcharset = 'UTF-8';
	//待处理网页地址
	private $file = '';
	//忽略连续链接的阀值，0表示保留链接
	private $ignore_links = 3;
	//空白阀值
	private $space_step = 3;
	
	
	public function __construct($html, $file='', $outcharset='UTF-8'){
		$this->set_html($html, $file);
		$this->outcharset = $outcharset;
	}
	
	/**
	 * 设置待处理html
	 */
	public function set_html($html, $file=''){
		if ($file) {
			$this->file = $file;
			$html = file_get_contents($file);
		}
		
		$this->html = $html;
		$this->content = '';
	}
	
	/**
	 * 允许的连续空行数
	 */
	public function space_step($num){
		$this->space_step = $num;
	}
	
	/**
	 * 设置忽略链接的阀值
	 */
	public function ignore_link($num){
		$this->ignore_links = $num;
	}
	
	/**
	 * 页面代码清理
	 * 
	 * 清除内容无关的代码
	 */
	private function _clear(){
		$replace_map = array(
			'/<!DOCTYPE.*?>/si'				=> '',	//过滤行首标签
			'/<!--.*?-->/s'					=> '',//过滤注释
			'/<script.*?>.*?<\/script>/si'	=> '', //过滤JS
			'/<style.*?>.*?<\/style>/si'	=> '', //过滤样式表
			'/<textarea.*?>.*?<\/textarea>/si'	=> '',//过滤文本框
			'/<input[^>]*?>.*?<\/input>/si'	=> '',//表单
			'/&.{1,5};|&#.{1,5};/i'			=> ' ',//忽略特殊符号
			'/\<\/?(?!img|a|p|br|\/).(.*?)\>/is'	=> '',
		);
		$html = trim($this->html);
		$html = $this->_clear_nr($html);//echo $html;//exit;
		$search = array_keys($replace_map);
		$replace = array_values($replace_map);
		$html = preg_replace($search, $replace, $html);
		return $html;
	}
	
	/**
	 * 清除重复的\n
	 */
	private function _clear_nr($string){
		if (empty($string)) {
			return '';
		}else{
			$string = preg_replace('/[(\n\r)(\n)(\n\s\r)]{2,}/', "\n", $string);
			//$string = preg_replace('/\n\s\n/', "\n", $string);	
			return $string;
		}
	}
	
	/**
	 * 验证内容块是否为链接
	 */
	private function _is_link($block){
		$ereg = array();
		$is_link = preg_match_all('/<\/?a[^>]*?>.*?/i', $block, $ereg);//var_dump($ereg);
		if ($is_link) {
			return $is_link;
		}else{
			return 0;
		}
	}

	public function extract_title($verdict=false){
		$regix = '/<(title)[^>]*?>(.*?)<\/(title)[^>]*?>/i';
		if(preg_match_all($regix, $this->html, $ereg)){
			foreach($ereg[2] as $item){
				$this->hn['title'][] = $this->_conv($item);
			}
		}
		$regix = '/<(h\d)[^>]*?>(.*?)<\/(h\d)>/i';
		if(preg_match_all($regix, $this->html, $ereg)){
			foreach($ereg[1] as $key=>$hn){
				$this->hn[$hn][] = $this->_conv(strip_tags($ereg[2][$key]));
			}
		}
		if ($verdict) {
			$tmp = array('h1', 'h2', 'h3', 'title', 'h4', 'h5', 'h6');
			foreach($tmp as $hn){
				if (isset($this->hn[$hn]) && count($this->hn[$hn])==1) {
					return $this->hn[$hn][0];
				}
			}
			return '';
		}else{
			return $this->hn;
		}
	}
	
	/**
	 * 将网页内容切为成块
	 */
	private function _split_to_block($html){
		$search = array(//将不同操作系统产生的换行标识统一置换为\n
			"\n", "\r\n", "\r"
		);
		$html = str_replace($search, "\n", $html);
		$this->block_array = explode("\n", $html);
		if ($this->block_array) {
			foreach ($this->block_array as $block) {
				$this->block_size[] = $this->_calc_block_len($block);
			}
		}
	}
	
	/**
	 * 计算内容块的长度
	 */
	private function _calc_block_len($block){
		$is_link = $this->_is_link($block);
		if($is_link){
			return -$is_link;
		}
		$search = array(
			'/<a[^>]*?>.*?<\/a>/is',//忽略链接
			'/<li[^>]*?>.*?<\/li>/i',	//忽略Li标签
			'/&.{1,5};|&#.{1,5};/',	//忽略特殊符号
			'/\s/',				//忽略连续空白
		);
		
		$block = preg_replace($search, '', $block);
		//echo $block, '&nbsp;&nbsp;',  strlen($block), '<br/>';
		return strlen($block);
	}
	
	/**
	 * 验证内容块是否为图片链接
	 */
	public function _is_image_block(){
		
	}
	
	/**
	 * 检测页面编码
	 * 
	 * 验证meta信息里charset的值
	 */
	public function _check_encoding(){
		$search = "/<meta.+?charset=[^\w]?([-\w]+)/i";
		$ereg = array();
		if(preg_match($search, $this->html, $ereg)){
			$encode = strtoupper($ereg[1]);
		}else{
			$encode = 'UTF-8';
		}
		$this->encode = $encode;
	}
	
	/**
	 * 页面内容转码
	 * 
	 * 自动检测网页编码
	 * 默认为utf-8
	 */
	public function _conv($html){
		if (!$this->encode) {
			$this->_check_encoding();
		}
		if($this->encode==$this->outcharset){
			return $html;
		}else{
			$html = iconv($this->encode, $this->outcharset, $html);
			return $html;
		}
	}
	
	/**
	 * 内容预处理
	 */
	private function _pre_process(){
		if ($this->content=='') {
			$html = $this->_clear();
			$html = $this->_conv($html);
			$this->_split_to_block($html);
		}
	}
	
	/**
	 * 提取网页内容
	 */
	public function get_content(){
		if (empty($this->content)) {
			$this->_pre_process();
			//var_dump($this->block_array, $this->block_size);exit;
			$block_max_len = 0;//最大的连续数据块
			$block_num = count($this->block_array);
			$space_step = 0;
			//var_dump($this->block_array, $this->block_size);exit;
			for($i=0; $i<$block_num; $i++){
				if($space_step>$this->space_step){
					//echo $space_step, $content, "\n";
					$content = '';
					$space_step = 0;
					$current_len = 0;
				}
				
				while($i<$block_num && $this->block_size[$i]!=0){
					$current_len += $this->block_size[$i];
					//过滤连续链接
					$link_str = '';
					$link_count = 0;//连续链接数
					while ($this->block_size[$i]<0) {
						$link_count += abs($this->block_size[$i]);
						$link_str .= $this->block_array[$i];
						$i++;
					}
					
					if($link_count>$this->ignore_links) continue;
					$content .= $this->block_array[$i];
					$space_step = 0;
					$i++;
				}
				
				//echo $content, "<br/>", $current_len, "<hr/>";
				$this->block_size[$i]==0 && $space_step++;
				//echo $this->block_array[$i], '<br/>';
				//echo "长度：$current_len ，链接数：$link_count ，$content<br/>";
				if ($current_len>$block_max_len) {
					$block_max_len = $current_len;
					$this->content = $content;
				}
			}
		}
		return $this->content;
	}
}



/*example*/
$url = 'http://digdeeply.org';
//实例化类，参数1直接传入html，参数2，指定网页地址 
$extract = new Extract('', $url); 
//忽略连续链接的阀值 
$extract->ignore_link($ignore_link); 
//允许连续空行数 
$extract->space_step($ignore_line); 
//提取网页标题 
$title = $extract->extract_title(true);
//获取正文 
$content = $extract->get_content();

var_dump("title:",$title,"content",$content);
