<?php
use PHPUnit\Framework\TestCase;
use App\Common\Visitor;
use App\Service\DemoService;
use App\Service\DataSourceService;
use App\Common\Util;

//业务主流程测试
class MainFlowTest extends TestCase
{
    public function testService()
    {
        $visitor = new Visitor(17712, "kindywu");
        $service = DemoService::Create($visitor);

        $pdo=Util::GetPDOInst();
        $result = $pdo->query("set names utf8");
        $this->assertTrue($result!=false, "错误");

        $service = DataSourceService::Create($visitor);
        $config = $service->CreateConifg(0, "127.0.0.1", "demo", "root", "828kindy@@");
		
        $pdo=$service->CreatePDO($config);
		$result = $pdo->query("set names utf8");
        $this->assertTrue($result!=false, "错误");
    }
}
