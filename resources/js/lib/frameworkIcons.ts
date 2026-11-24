/** Brand marks for detected site stacks (simple-icons, colored). */
export const FRAMEWORK_ICON_URL: Record<string, string> = {
    laravel: 'https://cdn.simpleicons.org/laravel/FF2D20',
    wordpress: 'https://cdn.simpleicons.org/wordpress/21759B',
    drupal: 'https://cdn.simpleicons.org/drupal/0678BE',
    symfony: 'https://cdn.simpleicons.org/symfony/000000',
    shopify: 'https://cdn.simpleicons.org/shopify/7AB55C',
    ghost: 'https://cdn.simpleicons.org/ghost/15171A',
};

export function frameworkIconUrl(
    framework: string | null | undefined,
): string | null {
    if (!framework) {
        return null;
    }

    const key = framework.toLowerCase();

    return FRAMEWORK_ICON_URL[key] ?? null;
}
