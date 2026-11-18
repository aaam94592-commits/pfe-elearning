document.getElementById('submitQuizBtn').addEventListener('click', function () {
    const answers = document.querySelectorAll('.answer');
    let score = 0;

    answers.forEach(a => {
        if (a.value.trim() !== "") {
            score++; // (غير مثال: كل جواب غير فارغ = نقطة)
        }
    });

    // envoyer avec AJAX
    const quiz_id = document.getElementById('quiz_id').value;

    let formData = new FormData();
    formData.append('quiz_id', quiz_id);
    formData.append('score', score);

    fetch('save_quiz_result.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('result').innerHTML =
                "تم إرسال نتيجتك بنجاح! النتيجة: " + score;
        }
    });
});
