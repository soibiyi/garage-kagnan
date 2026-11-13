<?php

namespace App\Notifications;

use App\Models\ErrorLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Prévient les super administrateurs dès qu'une nouvelle erreur survient dans l'application. */
class ErreurApplication extends Notification
{
    use Queueable;

    public function __construct(private readonly ErrorLog $erreur) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'erreur_id' => $this->erreur->id,
            'source' => $this->erreur->source,
            'type' => class_basename($this->erreur->type),
            'message' => Str::limit($this->erreur->message, 180),
            'url' => $this->erreur->url,
        ];
    }
}
