<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Mail;

class sendNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $title;
    protected $userEmail;
    public function __construct($title,$userEmail)
    {
        //
        $this->title=$title;
        $this->userEmail=$userEmail;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
        $content=date('Y-m-d H:i:s').'您的店铺有新的订单,请尽快处理';
        Mail::raw($content,function ($message){
            // 发件人（你自己的邮箱和名称）
            $message->from('pingran1993@163.com', 'eleb平台');
            // 收件人的邮箱地址
            $message->to($this->userEmail);
            // 邮件主题
            $message->subject($this->title);
    });
    }
}
