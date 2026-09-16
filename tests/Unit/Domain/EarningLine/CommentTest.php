<?php

declare(strict_types=1);

namespace Alcor\Payroll\Tests\Unit\Domain\EarningLine;

use Alcor\Payroll\Domain\EarningLine\Comment;
use Alcor\Payroll\Domain\EarningLine\Exception\InvalidComment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommentTest extends TestCase
{
    public function test_it_keeps_the_explanation_the_specialist_typed(): void
    {
        $comment = new Comment('Employee declined dental benefit; reversing deduction');

        self::assertSame('Employee declined dental benefit; reversing deduction', $comment->value);
    }

    public function test_it_trims_surrounding_whitespace(): void
    {
        self::assertSame('Late correction', new Comment("  Late correction \n")->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blankComments(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'tab and newline' => ["\t\n"];
    }

    #[DataProvider('blankComments')]
    public function test_an_adjustment_may_not_be_explained_by_nothing(string $value): void
    {
        $this->expectException(InvalidComment::class);

        new Comment($value);
    }

    public function test_it_accepts_the_longest_allowed_comment(): void
    {
        self::assertSame(Comment::MAX_LENGTH, mb_strlen(new Comment(str_repeat('a', Comment::MAX_LENGTH))->value));
    }

    public function test_it_rejects_a_comment_one_character_too_long(): void
    {
        $this->expectException(InvalidComment::class);

        new Comment(str_repeat('a', Comment::MAX_LENGTH + 1));
    }

    public function test_length_is_counted_in_characters_not_bytes(): void
    {
        $multibyte = str_repeat('я', Comment::MAX_LENGTH);

        self::assertSame($multibyte, new Comment($multibyte)->value);
    }
}
