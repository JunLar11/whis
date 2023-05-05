<?php class_exists('Whis\View\StencilEngine') or exit; ?>
<!DOCTYPE html>
<html lang="en">
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/bootswatch/5.1.3/darkly/bootstrap.min.css"
    integrity="sha512-ZdxIsDOtKj2Xmr/av3D/uo1g15yxNFjkhrcfLooZV5fW0TT7aF7Z3wY1LOA16h0VgFLwteg14lWqlYUQK3to/w=="
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
  />

  <script
    defer
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p"
    crossorigin="anonymous"
  ></script>

  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Home</title>
  </head>

  <body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
      <div class="container-fluid">
        <a class="navbar-brand" href="/">Navbar</a>
        <button
          class="navbar-toggler"
          type="button"
          data-bs-toggle="collapse"
          data-bs-target="#navbarSupportedContent"
          aria-controls="navbarSupportedContent"
          aria-expanded="false"
          aria-label="Toggle navigation"
        >
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
          <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
              <a class="nav-link active" aria-current="page" href="/form">Form</a>
            </li>
            <?php if (isGuest()): ?>
              <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="/login">Login</a>
              </li>
              <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="/register">Register</a>
              </li>
            <?php else: ?>
              <li class="nav-item">
                <a class="nav-link active" aria-current="page" href="/logout">Logout</a>
              </li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>
    <main class="container">
      <h1>Test Form</h1>
<form method="post">
  <div class="mb-3">
    <label class="form-label">Email</label>
    <input value="<?php echo old('email') ?>" name="email" type="text" class="form-control">
    <div class="text-danger"><?php echo error('email') ?></div>
  </div>

  <div class="mb-3">
    <label class="form-label">Name</label>
    <input value="<?php echo old('name') ?>" name="name" type="text" class="form-control">
    <div class="text-danger"><?php echo error('name') ?></div>

  </div>

  <button type="submit" class="btn btn-primary">Submit</button>
</form>
    </main>
  </body>
</html>