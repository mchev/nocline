import { describe, expect, it } from 'vitest';
import {
    wallboardGridLayout,
    wallboardSiteCaption,
    wallboardSiteHasTitle,
    wallboardSiteLabel,
} from '@/lib/wallboardLayout';

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

describe('wallboardSiteCaption', () => {
    it('shows a custom title once', () => {
        expect(
            wallboardSiteHasTitle({
                name: 'Boutique prod',
                target: 'https://shop.example.com',
            }),
        ).toBe(true);
        expect(
            wallboardSiteCaption({
                name: 'Boutique prod',
                target: 'https://shop.example.com',
            }),
        ).toBe('Boutique prod');
    });

    it('falls back to the URL when the name is only the address', () => {
        expect(
            wallboardSiteHasTitle({
                name: 'shop.example.com',
                target: 'https://shop.example.com/blog',
            }),
        ).toBe(false);
        expect(
            wallboardSiteCaption({
                name: 'shop.example.com',
                target: 'https://shop.example.com/blog',
            }),
        ).toBe('shop.example.com/blog');
    });
});