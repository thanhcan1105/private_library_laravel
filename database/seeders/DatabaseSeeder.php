<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('123456');

        DB::table('users')->insert([
            ['username' => 'admin', 'email' => 'admin@example.com', 'password' => $password, 'full_name' => 'Administrator', 'role' => 'admin'],
            ['username' => 'alice', 'email' => 'alice@example.com', 'password' => $password, 'full_name' => 'Alice Nguyen', 'role' => 'user'],
            ['username' => 'bob',   'email' => 'bob@example.com',   'password' => $password, 'full_name' => 'Bob Tran',     'role' => 'user'],
            ['username' => 'carol', 'email' => 'carol@example.com', 'password' => $password, 'full_name' => 'Carol Le',     'role' => 'user'],
        ]);

        DB::table('reading_settings')->insert([
            ['user_id' => 1], ['user_id' => 2], ['user_id' => 3], ['user_id' => 4],
        ]);

        DB::table('genres')->insert([
            ['name' => 'Tiểu thuyết', 'description' => 'Truyện dài với cốt truyện và hệ thống nhân vật phát triển.'],
            ['name' => 'Truyện ngắn', 'description' => 'Tác phẩm tự sự có dung lượng ngắn, tập trung vào một lát cắt câu chuyện.'],
            ['name' => 'Trinh thám', 'description' => 'Truyện xoay quanh vụ án, manh mối và quá trình điều tra.'],
            ['name' => 'Khoa học viễn tưởng', 'description' => 'Tác phẩm khai thác khoa học, công nghệ và những viễn cảnh tương lai.'],
            ['name' => 'Kỳ ảo', 'description' => 'Truyện có yếu tố phép thuật, thế giới tưởng tượng hoặc siêu nhiên.'],
            ['name' => 'Lãng mạn', 'description' => 'Tác phẩm tập trung vào tình yêu và các mối quan hệ tình cảm.'],
            ['name' => 'Kinh dị', 'description' => 'Tác phẩm tạo không khí hồi hộp, bí ẩn hoặc đáng sợ.'],
            ['name' => 'Phiêu lưu', 'description' => 'Những hành trình khám phá và thử thách của nhân vật.'],
            ['name' => 'Lịch sử', 'description' => 'Sách về sự kiện, nhân vật và bối cảnh lịch sử.'],
            ['name' => 'Tâm lý', 'description' => 'Tác phẩm về cảm xúc, suy nghĩ và hành vi con người.'],
            ['name' => 'Kỹ năng sống', 'description' => 'Sách chia sẻ kỹ năng thực tế cho học tập, công việc và cuộc sống.'],
            ['name' => 'Thiếu nhi', 'description' => 'Sách và truyện dành cho độc giả nhỏ tuổi.'],
            ['name' => 'Khoa học phổ thông', 'description' => 'Sách giới thiệu kiến thức khoa học theo cách dễ tiếp cận.'],
        ]);

        DB::table('authors')->insert([
            ['name' => 'Nguyen Nhat Anh'], ['name' => 'Nam Cao'],
        ]);
    }
}
