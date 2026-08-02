/**
 * Cute animal avatars — strictly no file uploads, ever.
 *
 * Every avatar is a free, hotlinkable photo URL (Unsplash CDN / Wikimedia
 * Commons) plus an emoji fallback so a player card still looks great even if
 * a network request fails. All URLs were verified to serve real images.
 */
export const ANIMALS = [
    {
        key: 'bear',
        name: 'Bear',
        emoji: '🐻',
        url: 'https://images.unsplash.com/photo-1530595467537-0b5996c41f2d?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'fox',
        name: 'Fox',
        emoji: '🦊',
        url: 'https://images.unsplash.com/photo-1474511320723-9a56873867b5?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'panda',
        name: 'Panda',
        emoji: '🐼',
        url: 'https://images.unsplash.com/photo-1525088553748-01d6e210e00b?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'bunny',
        name: 'Bunny',
        emoji: '🐰',
        url: 'https://images.unsplash.com/photo-1585110396000-c9ffd4e4b308?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'koala',
        name: 'Koala',
        emoji: '🐨',
        url: 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'otter',
        name: 'Otter',
        emoji: '🦦',
        url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/f/f8/Sea-otter-morro-bay_13.jpg/500px-Sea-otter-morro-bay_13.jpg',
    },
    {
        key: 'cat',
        name: 'Cat',
        emoji: '🐱',
        url: 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'dog',
        name: 'Dog',
        emoji: '🐶',
        url: 'https://images.unsplash.com/photo-1517849845537-4d257902454a?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'lion',
        name: 'Lion',
        emoji: '🦁',
        url: 'https://images.unsplash.com/photo-1546182990-dffeafbe841d?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'penguin',
        name: 'Penguin',
        emoji: '🐧',
        url: 'https://images.unsplash.com/photo-1551986782-d0169b3f8fa7?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'red-panda',
        name: 'Red Panda',
        emoji: '🐾',
        url: 'https://images.unsplash.com/photo-1564349683136-77e08dba1ef7?w=400&h=400&fit=crop&crop=faces',
    },
    {
        key: 'kitten',
        name: 'Kitten',
        emoji: '😺',
        url: 'https://images.unsplash.com/photo-1518791841217-8f162f1e1131?w=400&h=400&fit=crop&crop=faces',
    },
];

/** Grab a random critter for a brand-new guest player. */
export function randomAnimal() {
    return ANIMALS[Math.floor(Math.random() * ANIMALS.length)];
}
