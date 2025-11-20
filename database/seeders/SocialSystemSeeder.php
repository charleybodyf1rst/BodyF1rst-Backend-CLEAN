<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AvatarItem;
use App\Models\SocialShareReward;

class SocialSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create initial avatar items
        $this->seedAvatarItems();

        // Create social share rewards
        $this->seedSocialShareRewards();
    }

    private function seedAvatarItems()
    {
        $avatarItems = [
            // Free starter items
            [
                'name' => 'Basic Black T-Shirt',
                'description' => 'Simple black athletic t-shirt',
                'item_type' => 'clothing',
                'rarity' => 'common',
                'unlock_cost' => 0,
                'unlock_method' => 'free',
                'is_premium' => false
            ],
            [
                'name' => 'Basic White T-Shirt',
                'description' => 'Simple white athletic t-shirt',
                'item_type' => 'clothing',
                'rarity' => 'common',
                'unlock_cost' => 0,
                'unlock_method' => 'free',
                'is_premium' => false
            ],
            [
                'name' => 'Short Hair',
                'description' => 'Simple short hairstyle',
                'item_type' => 'hairstyle',
                'rarity' => 'common',
                'unlock_cost' => 0,
                'unlock_method' => 'free',
                'is_premium' => false
            ],

            // Points-based unlocks
            [
                'name' => 'Red Sports Jersey',
                'description' => 'Bold red athletic jersey',
                'item_type' => 'clothing',
                'rarity' => 'uncommon',
                'unlock_cost' => 100,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Blue Hoodie',
                'description' => 'Comfortable blue athletic hoodie',
                'item_type' => 'clothing',
                'rarity' => 'uncommon',
                'unlock_cost' => 150,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Athletic Headband',
                'description' => 'Sporty headband accessory',
                'item_type' => 'accessory',
                'rarity' => 'uncommon',
                'unlock_cost' => 75,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Wristbands',
                'description' => 'Classic athletic wristbands',
                'item_type' => 'accessory',
                'rarity' => 'common',
                'unlock_cost' => 50,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Long Hair',
                'description' => 'Flowing long hairstyle',
                'item_type' => 'hairstyle',
                'rarity' => 'uncommon',
                'unlock_cost' => 100,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Mohawk',
                'description' => 'Bold mohawk hairstyle',
                'item_type' => 'hairstyle',
                'rarity' => 'rare',
                'unlock_cost' => 250,
                'unlock_method' => 'points',
                'is_premium' => false
            ],

            // Social share rewards
            [
                'name' => 'Social Butterfly Badge',
                'description' => 'Earned for sharing your first achievement',
                'item_type' => 'badge',
                'rarity' => 'uncommon',
                'unlock_cost' => 0,
                'unlock_method' => 'social_share',
                'unlock_requirements' => ['shares' => 1],
                'is_premium' => false
            ],
            [
                'name' => 'Influencer Crown',
                'description' => 'Earned for 10 social media shares',
                'item_type' => 'accessory',
                'rarity' => 'rare',
                'unlock_cost' => 0,
                'unlock_method' => 'social_share',
                'unlock_requirements' => ['shares' => 10],
                'is_premium' => false
            ],
            [
                'name' => 'Golden Glow Effect',
                'description' => 'Legendary glow effect for social champions',
                'item_type' => 'effect',
                'rarity' => 'legendary',
                'unlock_cost' => 0,
                'unlock_method' => 'social_share',
                'unlock_requirements' => ['shares' => 25],
                'is_premium' => false
            ],

            // Premium items
            [
                'name' => 'Diamond Sneakers',
                'description' => 'Exclusive diamond-studded sneakers',
                'item_type' => 'clothing',
                'rarity' => 'legendary',
                'unlock_cost' => 0,
                'unlock_method' => 'purchase',
                'is_premium' => true
            ],
            [
                'name' => 'Golden Watch',
                'description' => 'Luxury golden fitness watch',
                'item_type' => 'accessory',
                'rarity' => 'epic',
                'unlock_cost' => 0,
                'unlock_method' => 'purchase',
                'is_premium' => true
            ],

            // Backgrounds
            [
                'name' => 'Gym Background',
                'description' => 'Modern gym setting',
                'item_type' => 'background',
                'rarity' => 'common',
                'unlock_cost' => 100,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Beach Background',
                'description' => 'Tropical beach setting',
                'item_type' => 'background',
                'rarity' => 'uncommon',
                'unlock_cost' => 200,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Mountain Peak Background',
                'description' => 'Epic mountain summit',
                'item_type' => 'background',
                'rarity' => 'rare',
                'unlock_cost' => 500,
                'unlock_method' => 'points',
                'is_premium' => false
            ],

            // Effects
            [
                'name' => 'Fire Aura',
                'description' => 'Blazing fire effect around avatar',
                'item_type' => 'effect',
                'rarity' => 'epic',
                'unlock_cost' => 1000,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Lightning Sparks',
                'description' => 'Electric sparks effect',
                'item_type' => 'effect',
                'rarity' => 'rare',
                'unlock_cost' => 750,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Star Power',
                'description' => 'Glittering stars effect',
                'item_type' => 'effect',
                'rarity' => 'uncommon',
                'unlock_cost' => 300,
                'unlock_method' => 'points',
                'is_premium' => false
            ],

            // Emotes
            [
                'name' => 'Flex Emote',
                'description' => 'Show off those gains',
                'item_type' => 'emote',
                'rarity' => 'common',
                'unlock_cost' => 50,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Victory Dance',
                'description' => 'Celebrate your achievements',
                'item_type' => 'emote',
                'rarity' => 'uncommon',
                'unlock_cost' => 150,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
            [
                'name' => 'Meditation Pose',
                'description' => 'Find your zen',
                'item_type' => 'emote',
                'rarity' => 'uncommon',
                'unlock_cost' => 125,
                'unlock_method' => 'points',
                'is_premium' => false
            ],
        ];

        foreach ($avatarItems as $item) {
            AvatarItem::create($item);
        }

        $this->command->info('Created ' . count($avatarItems) . ' avatar items');
    }

    private function seedSocialShareRewards()
    {
        $rewards = [
            [
                'share_type' => 'first_share',
                'points_reward' => 100,
                'avatar_items_reward' => [10], // Social Butterfly Badge
                'max_claims_per_user' => 1,
                'is_active' => true
            ],
            [
                'share_type' => 'workout_share',
                'points_reward' => 25,
                'avatar_items_reward' => null,
                'max_claims_per_user' => 50, // Can earn up to 50 times
                'is_active' => true
            ],
            [
                'share_type' => 'nutrition_share',
                'points_reward' => 20,
                'avatar_items_reward' => null,
                'max_claims_per_user' => 50,
                'is_active' => true
            ],
            [
                'share_type' => 'achievement_share',
                'points_reward' => 50,
                'avatar_items_reward' => null,
                'max_claims_per_user' => 100,
                'is_active' => true
            ],
            [
                'share_type' => 'badge_share',
                'points_reward' => 75,
                'avatar_items_reward' => null,
                'max_claims_per_user' => 100,
                'is_active' => true
            ],
            [
                'share_type' => 'progression_share',
                'points_reward' => 100,
                'avatar_items_reward' => null,
                'max_claims_per_user' => 25,
                'is_active' => true
            ],
        ];

        foreach ($rewards as $reward) {
            SocialShareReward::create($reward);
        }

        $this->command->info('Created ' . count($rewards) . ' social share rewards');
    }
}
