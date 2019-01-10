<div class="box box-info">
    <div class="box-header with-border">
        <h3 class="box-title">{{$type->name}}</h3>
    </div>
    <div class="box-body">
        <form action="{{route('admin.country.save', ['id' => $type->id])}}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="">Название</span>
                        <input type="text" class="form-control" placeholder="Имя роли" name="name" value="{{old('name')??$type->name}}" @if($type->name == 'admin') disabled @endif>
                    </div>

                    @if ($errors->has('name'))
                        <span class="invalid-feedback text-red" role="alert">
                                <strong>{{ $errors->first('name') }}</strong>
                            </span>
                    @endif
                    <br>
                    <div class="input-group">
                        <span class="">Код страны</span>
                        <input type="text" name="code" class="form-control" placeholder="Название роли" value="{{old('code')??$type->code}}">
                    </div>
                    @if ($errors->has('code'))
                        <span class="invalid-feedback text-red" role="alert">
                                <strong>{{ $errors->first('code') }}</strong>
                            </span>
                    @endif
                </div>
            </div>
            <div class="box-footer">
                <button type="submit" class="btn btn-info pull-right">Обновить</button>
            </div>
        </form>
    </div>
    <!-- /.box-body -->
</div>