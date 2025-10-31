<?php

namespace LaravelWhatsApp\Enums;


enum MimeType : string
{
    case AUDIO_AAC = 'audio/aac';
    case AUDIO_AMR = 'audio/amr';
    case AUDIO_MPEG = 'audio/mpeg';
    case AUDIO_MP4 = 'audio/mp4';
    case AUDIO_OGG = 'audio/ogg';
    
    case DOCUMENT_TEXT = 'text/plain';
    case DOCUMENT_XLS = 'application/vnd.ms-excel';
    case DOCUMENT_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    case DOCUMENT_DOC = 'application/msword';
    case DOCUMENT_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    case DOCUMENT_PPT = 'application/vnd.ms-powerpoint';
    case DOCUMENT_PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    case DOCUMENT_PDF = 'application/pdf';

    case IMAGE_JPEG = 'image/jpeg';
    case IMAGE_PNG = 'image/png';

    case STICKER_WEBP = 'image/webp';

    case VIDEO_3GPP = 'video/3gpp';
    case VIDEO_MP4 = 'video/mp4';

    public function category(): string
    {
        return explode('_', $this->name)[0];
    }

    public function isCategory(string $category): bool
    {
        return $this->category() === strtoupper($category);
    }

    public static function categoryTypes(): array
    {
        return [
            'AUDIO' => [
                self::AUDIO_AAC,
                self::AUDIO_AMR,
                self::AUDIO_MPEG,
                self::AUDIO_MP4,
                self::AUDIO_OGG,
            ],
            'DOCUMENT' => [
                self::DOCUMENT_TEXT,
                self::DOCUMENT_XLS,
                self::DOCUMENT_XLSX,
                self::DOCUMENT_DOC,
                self::DOCUMENT_DOCX,
                self::DOCUMENT_PPT,
                self::DOCUMENT_PPTX,
                self::DOCUMENT_PDF,
            ],
            'IMAGE' => [
                self::IMAGE_JPEG,
                self::IMAGE_PNG,
            ],
            'STICKER' => [
                self::STICKER_WEBP,
            ],
            'VIDEO' => [
                self::VIDEO_3GPP,
                self::VIDEO_MP4,
            ],
        ];
    }

    public function fileExtension(): string
    {
        return match($this) {
            self::AUDIO_AAC => 'aac',
            self::AUDIO_AMR => 'amr',
            self::AUDIO_MPEG => 'mp3',
            self::AUDIO_MP4 => 'mp4',
            self::AUDIO_OGG => 'ogg',
            self::DOCUMENT_TEXT => 'txt',
            self::DOCUMENT_XLS => 'xls',
            self::DOCUMENT_XLSX => 'xlsx',
            self::DOCUMENT_DOC => 'doc',
            self::DOCUMENT_DOCX => 'docx',
            self::DOCUMENT_PPT => 'ppt',
            self::DOCUMENT_PPTX => 'pptx',
            self::DOCUMENT_PDF => 'pdf',
            self::IMAGE_JPEG => 'jpg',
            self::IMAGE_PNG => 'png',
            self::STICKER_WEBP => 'webp',
            self::VIDEO_3GPP => '3gp',
            self::VIDEO_MP4 => 'mp4',
        };
    }



}