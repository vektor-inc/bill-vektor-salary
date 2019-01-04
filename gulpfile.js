var gulp = require('gulp');

// copy dist ////////////////////////////////////////////////

gulp.task('dist', function() {
	return gulp.src(
			[
				'./**/*.php',
				'./**/*.txt',
				'./**/*.css',
				'./**/*.scss',
				'./**/*.bat',
				'./**/*.rb',
				'./**/*.eot',
				'./**/*.svg',
				'./**/*.ttf',
				'./**/*.woff',
				'./images/**',
				'./inc/**',
				'./js/**',
				'./languages/**',
				'./library/**',
				"!./tests/**",
				"!./dist/**",
        "!./**/compile.bat",
				"!./node_modules/**/*.*"
			], {
				base: './'
			}
		)
		.pipe(gulp.dest('dist/bill-vektor-other-docs')); // distディレクトリに出力
});
