# Persona overlays

このディレクトリは、base harness に対して **persona 別の期待値差分** を重ねるための予約領域です。

## 役割

- `#106` では base harness のみを正本にする
- `#108` で `operator` / `administrator` / `field-leader` の expected-first-steps をここへ追加する

## 置く予定のもの

- `operator/README.md`
- `administrator/README.md`
- `field-leader/README.md`

## 置かないもの

- runtime settings の正本
- clean-room の global invariant
- `GEMINI_CLI_HOME` 分離の基礎条件

それらは `../README.md` と `../base/` を正本にします。

