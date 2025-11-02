<?php

use LaravelWhatsApp\Enums\MimeType;

it('returns correct category types as static method', function () {
    $categoryTypes = MimeType::categoryTypes();

    expect($categoryTypes)->toBeArray()
        ->and($categoryTypes)->toHaveKeys(['AUDIO', 'DOCUMENT', 'IMAGE', 'STICKER', 'VIDEO']);

    // Test AUDIO category
    expect($categoryTypes['AUDIO'])->toContain(
        MimeType::AUDIO_AAC,
        MimeType::AUDIO_AMR,
        MimeType::AUDIO_MPEG,
        MimeType::AUDIO_MP4,
        MimeType::AUDIO_OGG
    );

    // Test DOCUMENT category
    expect($categoryTypes['DOCUMENT'])->toContain(
        MimeType::DOCUMENT_TEXT,
        MimeType::DOCUMENT_XLS,
        MimeType::DOCUMENT_XLSX,
        MimeType::DOCUMENT_DOC,
        MimeType::DOCUMENT_DOCX,
        MimeType::DOCUMENT_PPT,
        MimeType::DOCUMENT_PPTX,
        MimeType::DOCUMENT_PDF
    );

    // Test IMAGE category
    expect($categoryTypes['IMAGE'])->toContain(
        MimeType::IMAGE_JPEG,
        MimeType::IMAGE_PNG
    );

    // Test STICKER category
    expect($categoryTypes['STICKER'])->toContain(
        MimeType::STICKER_WEBP
    );

    // Test VIDEO category
    expect($categoryTypes['VIDEO'])->toContain(
        MimeType::VIDEO_3GPP,
        MimeType::VIDEO_MP4
    );
});

it('can get category from mime type instance', function () {
    expect(MimeType::IMAGE_JPEG->category())->toBe('IMAGE')
        ->and(MimeType::AUDIO_MP4->category())->toBe('AUDIO')
        ->and(MimeType::DOCUMENT_PDF->category())->toBe('DOCUMENT')
        ->and(MimeType::VIDEO_MP4->category())->toBe('VIDEO')
        ->and(MimeType::STICKER_WEBP->category())->toBe('STICKER');
});

it('can check if mime type is in category', function () {
    expect(MimeType::IMAGE_JPEG->isCategory('IMAGE'))->toBeTrue()
        ->and(MimeType::IMAGE_JPEG->isCategory('AUDIO'))->toBeFalse()
        ->and(MimeType::AUDIO_MP4->isCategory('AUDIO'))->toBeTrue()
        ->and(MimeType::DOCUMENT_PDF->isCategory('DOCUMENT'))->toBeTrue();
});

it('returns correct file extensions', function () {
    expect(MimeType::IMAGE_JPEG->fileExtension())->toBe('jpg')
        ->and(MimeType::IMAGE_PNG->fileExtension())->toBe('png')
        ->and(MimeType::DOCUMENT_PDF->fileExtension())->toBe('pdf')
        ->and(MimeType::VIDEO_MP4->fileExtension())->toBe('mp4')
        ->and(MimeType::AUDIO_MP4->fileExtension())->toBe('mp4');
});

it('can be created from string value', function () {
    expect(MimeType::tryFrom('image/jpeg'))->toBe(MimeType::IMAGE_JPEG)
        ->and(MimeType::tryFrom('audio/mp4'))->toBe(MimeType::AUDIO_MP4)
        ->and(MimeType::tryFrom('application/pdf'))->toBe(MimeType::DOCUMENT_PDF)
        ->and(MimeType::tryFrom('invalid/mime'))->toBeNull();
});

it('has correct string values', function () {
    expect(MimeType::IMAGE_JPEG->value)->toBe('image/jpeg')
        ->and(MimeType::AUDIO_MP4->value)->toBe('audio/mp4')
        ->and(MimeType::DOCUMENT_PDF->value)->toBe('application/pdf')
        ->and(MimeType::VIDEO_MP4->value)->toBe('video/mp4');
});
