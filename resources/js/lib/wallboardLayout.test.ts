import { describe, expect, it } from 'vitest';
import { wallboardGridLayout, wallboardSiteLabel } from '@/lib/wallboardLayout';

describe('wallboardGridLayout', () => {
    it('fills a wide pane with more columns', () => {
        const wide = wallboardGridLayout(50, 1200, 600);
        const narrow = wallboardGridLayout(50, 600, 600);

        expect(wide.cols).toBeGreaterThan(narrow.cols);
        expect(wide.cols * wide.rows).toBeGreaterThanOrEqual(50);
    });
});

describe('wallboardSiteLabel', () => {
    it('prefers the hostname for long names', () => {
        expect(
            wallboardSiteLabel({
                name: 'Production shop — https://shop.example.com',
                target: 'https://www.shop.example.com/path',
            }),
        ).toBe('shop.example.com');
    });
});