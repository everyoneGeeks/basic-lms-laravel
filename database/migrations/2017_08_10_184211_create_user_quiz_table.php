    <?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserQuizTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_quiz', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('quiz_id')->unsigned()->nullable();
            $table->foreign('quiz_id', 'fk_256_quiz_quiz_id_quiz')->references('id')->on('quizzes')->onDelete('cascade');;
            $table->integer('user_id')->unsigned()->nullable();
            $table->foreign('user_id', 'fk_256_user_quiz_id_user')->references('id')->on('users')->onDelete('cascade');;
            $table->string('processing_status');
            $table->float('grade')->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_quiz');
    }
}
