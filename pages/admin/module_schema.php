<?php

namespace RoadRanger;

class ModuleSchema
{
    public static function normalizeGeneratedModule(array $generated, string $defaultTitle = 'Road Safety Lesson'): array
    {
        $title = trim((string)($generated['title'] ?? $defaultTitle));
        if ($title === '') {
            $title = $defaultTitle;
        }

        $nodes = [];
        if (isset($generated['nodes']) && is_array($generated['nodes'])) {
            $nodes = $generated['nodes'];
        } elseif (isset($generated['content']) || isset($generated['summary']) || isset($generated['quiz'])) {
            $nodes = [];
        } else {
            $nodes = $generated;
        }

        $coverImage = '';
        $summary = trim((string)($generated['summary'] ?? ''));

        if (isset($generated['cover_image']) && is_string($generated['cover_image'])) {
            $coverImage = trim($generated['cover_image']);
        }

        $startNode = null;
        if (isset($nodes['start']) && is_array($nodes['start'])) {
            $startNode = $nodes['start'];
        } else {
            foreach ($nodes as $node) {
                if (is_array($node)) {
                    $startNode = $node;
                    break;
                }
            }
        }

        if ($coverImage === '' && is_array($startNode)) {
            $coverImage = self::extractImageUrl($startNode['image'] ?? null);
        }

        if ($summary === '') {
            $summary = self::extractNodeMessage($startNode);
        }

        $content = [];
        if (isset($generated['content']) && is_array($generated['content'])) {
            foreach ($generated['content'] as $section) {
                if (!is_array($section)) {
                    continue;
                }

                $sectionText = trim((string)($section['text'] ?? $section['body'] ?? $section['content'] ?? ''));
                if ($sectionText === '' && isset($section['bot_message'])) {
                    $sectionText = trim((string)$section['bot_message']);
                }

                if ($sectionText === '') {
                    continue;
                }

                $content[] = [
                    'heading' => trim((string)($section['heading'] ?? self::humanizeSectionName((string)($section['title'] ?? 'Lesson point')))),
                    'text' => $sectionText,
                    'image' => self::extractImageUrl($section['image'] ?? null),
                ];
            }
        }

        if (empty($content) && !empty($nodes)) {
            foreach ($nodes as $key => $node) {
                if (!is_array($node)) {
                    continue;
                }

                $nodeText = trim((string)($node['bot_message'] ?? $node['text'] ?? ''));
                if ($nodeText === '') {
                    continue;
                }

                if (strtolower((string)$key) === 'start' && $summary === '') {
                    $summary = $nodeText;
                }

                $content[] = [
                    'heading' => self::humanizeSectionName((string)$key),
                    'text' => $nodeText,
                    'image' => self::extractImageUrl($node['image'] ?? null),
                ];
            }
        }

        if ($summary === '' && !empty($content)) {
            $summary = trim((string)($content[0]['text'] ?? ''));
        }

        $quiz = [];
        if (isset($generated['quiz']) && is_array($generated['quiz'])) {
            $quiz = $generated['quiz'];
        } else {
            foreach ($nodes as $key => $node) {
                if (!is_array($node) || empty($node['choices'])) {
                    continue;
                }

                $questionText = trim((string)($node['bot_message'] ?? $node['text'] ?? $node['question'] ?? ''));
                if ($questionText === '') {
                    continue;
                }

                $options = [];
                $correctOptionSet = false;
                foreach ($node['choices'] as $choice) {
                    if (!is_array($choice)) {
                        continue;
                    }

                    $optionText = trim((string)($choice['text'] ?? ''));
                    if ($optionText === '') {
                        continue;
                    }

                    $isCorrect = isset($choice['score_impact']) && (float)$choice['score_impact'] > 0;
                    if (!$correctOptionSet && $isCorrect) {
                        $correctOptionSet = true;
                    }

                    $options[] = [
                        'text' => $optionText,
                        'correct' => $isCorrect,
                    ];
                }

                if (empty($options)) {
                    continue;
                }

                if (!$correctOptionSet && !empty($options)) {
                    $options[0]['correct'] = true;
                }

                $quiz[] = [
                    'question' => $questionText,
                    'options' => $options,
                    'explanation' => 'Review the lesson and choose the safest answer.',
                ];
            }
        }

        if (empty($quiz)) {
            $quiz[] = [
                'question' => 'What is the most important safety action from this module?',
                'options' => [
                    ['text' => 'Follow the lesson instructions carefully.', 'correct' => true],
                    ['text' => 'Ignore the lesson and proceed without checking.', 'correct' => false],
                    ['text' => 'Only focus on the lesson title.', 'correct' => false],
                ],
                'explanation' => 'The module teaches the rule that safe road behavior is based on the lesson content and the correct action.',
            ];
        }

        return [
            'title' => $title,
            'summary' => $summary,
            'cover_image' => $coverImage,
            'content' => $content,
            'quiz' => $quiz,
            'pass_score' => 60,
        ];
    }

    private static function extractNodeMessage($node): string
    {
        if (!is_array($node)) {
            return '';
        }

        $message = trim((string)($node['bot_message'] ?? $node['text'] ?? ''));
        if ($message !== '') {
            return $message;
        }

        foreach (($node['choices'] ?? []) as $choice) {
            if (is_array($choice) && isset($choice['text'])) {
                $optionText = trim((string)$choice['text']);
                if ($optionText !== '') {
                    return $optionText;
                }
            }
        }

        return '';
    }

    private static function extractImageUrl($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        return $trimmed;
    }

    private static function humanizeSectionName(string $label): string
    {
        $cleaned = preg_replace('/[_-]+/', ' ', $label);
        $cleaned = preg_replace('/\s+/', ' ', trim((string)$cleaned));
        if ($cleaned === '') {
            return 'Lesson Point';
        }

        return ucwords(strtolower($cleaned));
    }
}
