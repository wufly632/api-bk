<?php

namespace App\Jobs;

use App\Jobs\Job;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use App\Assistants\CLogger;

class CatchApiRequestLog  extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    protected $status;
    protected $method;
    protected $url;
    protected $p;
    protected $response;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($status,$method,$url,$p,$response)
    {
        $this->status = $status;
        $this->method = $method;
        $this->url = $url;
        $this->p = $p;
        $this->response = $response;
    }

    /**
     * 正常info的日志
     *
     * @return \Monolog\Logger
     */
    public function infoLogger(){
        return CLogger::getLogger('api_request','webservice');
    }

    /**
     * 记录错误的日志
     *
     * @return \Monolog\Logger
     */
    public function errorLogger(){
        return CLogger::getLogger('api_request_error','webservice');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = [
            'status'=>$this->status,
            'method'=>$this->method,
            'url'=>$this->url,
            'parameters'=>$this->p,
            'response'=>$this->response
        ];
        if($this->status == g_STATUSCODE_OK){
            $this->infoLogger()->info('',$data);
        }else{
            $this->errorLogger()->info('',$data);
        }
    }

}
