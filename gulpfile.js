var gulp = require('gulp');

// copy dist ////////////////////////////////////////////////

gulp.task('dist', function() {
	return gulp.src(
			[
				'./**/*.php',
				'./**/*.txt',
				'./**/*.css',
				'./**/*.scss',
				'./**/*.js',
				'./**/*.bat',
				'./**/*.rb',
				'./**/*.eot',
				'./**/*.svg',
				'./**/*.ttf',
				'./**/*.woff',
				'./inc/**',
				// mPDF の辞書・フォントを拡張子に関係なく含める。
				'./vendor/**',
				"!./tests/**",
				"!./bin/**",
				"!./dist/**",
        "!./**/compile.bat",
				"!./node_modules/**/*.*"
			], {
				base: './',
				dot: true,
				// Gulp 5 の文字列変換によるフォント・辞書の破損を防ぐ。
				encoding: false
			}
		)
		.pipe(gulp.dest('dist/bill-vektor-salary')); // distディレクトリに出力
});
