<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\ModHelp;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;
use Posttwo\FunnyJunk\FunnyJunk;

class DiscordHelpController extends \App\Http\Controllers\Controller
{
    public function sendHelpRequest(Request $request)
    {
        if (Auth::user()->cannot('mod.isAMod')) {
            abort(403);
        }
    
        // Find if already exists
        try {
            $content = ModHelp::where('image_id', $request->input('imageId'))
                              ->where('comment_id', $request->input('commentId'))
                              ->firstOrFail();
            return response()->json('Already Asked', 406);
        } catch (ModelNotFoundException $e) {
            $content = new ModHelp;
            $content->content_id = $request->input('contentId');
            $content->content_url = $request->input('contentUrl');
            $content->image_id = $request->input('imageId');
            $content->image_url = $request->input('imageUrl');
            $content->comment_id = $request->input('commentId', null);
            $content->save();
    
            // Post it
            $fj = $this->fj->getByUrl($content->content_url);
            $slack = new \App\Slack;
            $slack->target = 'mod-help';
            $slack->username = Auth::user()->nickname;
            $slack->avatar = Auth::user()->avatar;
    
            if ($content->comment_id != null) {
                // COMMENT
                $slack->title = "Comment #" . $content->comment_id;
                $slack->text = 'Comment: https://funnyjunk.com/find/comment/' . $content->comment_id;
            } else {
                // CONTENT
                $slack->title = $fj->title;
                $slack->text = 'Content: https://funnyjunk.com' . $fj->base_url . '#' . $content->image_id;
            }
    
            $slack->text .= "\nImage: " . $content->image_url;
            $slack->embedFields = [
                'Posted By' => $fj->username,
                'Date: ' => $fj->date
            ];
            $slack->footer = Auth::user()->fjuser->username;
            $slack->image_url = $content->image_url;
    
            if ($fj->is_mature == 1) {
                $slack->color = 'error';
                $slack->text .= "\n :warning: **NSFW** :warning:";
            }
    
            // Send the notification
            \Notification::send($slack, new \App\Notifications\ModNotify(null));
    
            // Post to Discord and add reactions
            $discordMessage = $this->postToDiscord($slack);
            if ($discordMessage) {
                $this->addReactionsToDiscordMessage($discordMessage->id, $discordMessage->channel_id);
            }
    
            return response()->json('OK', 200);
        }
    }
    
    private function postToDiscord($slack)
    {
        $discordWebhookUrl = env('DISCORD_WEBHOOK_URL');
        $roleId = '453689421238370304';
        $message = [
            'username' => $slack->username,
            'avatar_url' => $slack->avatar,
            'content' => "<@&{$roleId}> " . $slack->text,
        ];
    
        $client = new \GuzzleHttp\Client();
        try {
            $response = $client->post($discordWebhookUrl, [
                'json' => $message
            ]);
    
            return json_decode($response->getBody());
        } catch (\Exception $e) {
            \Log::error('Error posting to Discord: ' . $e->getMessage());
            return null;
        }
    }
    
    private function addReactionsToDiscordMessage($messageId, $channelId)
    {
	//POSTTWO CHANGE THIS NIGGA
        $botToken = env('DISCORD_BOT_TOKEN');
        $reactions = ['<:green:308689157620760576>', '<:red:308689155657826305>'];
    
        $client = new \GuzzleHttp\Client();
        foreach ($reactions as $reaction) {
            $url = "https://discord.com/api/v9/channels/$channelId/messages/$messageId/reactions/" . urlencode($reaction) . "/@me";
            try {
                $client->put($url, [
                    'headers' => [
                        'Authorization' => 'Bot ' . $botToken
                    ]
                ]);
            } catch (\Exception $e) {
                \Log::error('Error adding reaction to Discord message: ' . $e->getMessage());
            }
        }
    }
}    
