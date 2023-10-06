<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\Card;
use App\Models\Installment;

class TransactionController extends Controller
{

    public function store(Request $request): JsonResponse
    {

        $request->validate([
            'transaction_description' => ['required', 'string', 'max:50'],
            'category_id' => ['required'],
            'category_description' => ['required_if:category_id,0', 'string', 'max:50'],
            'date' => ['required', 'date'],
            'type_id' => ['required', 'min:1', 'max:4'],
            'transaction_value' => ['required', 'decimal:0,2'],
            'payment_method_id' => ['required_if:type_id,2', 'prohibited_if:type_id,1'],
            'installments' => ['required_if:payment_method_id,4', 'prohibited_if:type_id,1'],
            'card_id' => ['required_if:payment_method_id,4', 'prohibited_if:type_id,1']
        ]);

        // Validando card id

        if ($request->payment_method_id == 4) {

            try {

                Card::findOrFail($request->card_id);
            } catch (\Exception $e) {
                $errorMessage = "Error: Cartão não encontrado.";
                $response = [
                    "data" => [
                        "message" => $errorMessage,
                        "error" => $e
                    ]
                ];

                return response()->json($response, 404);
            }
        }

        // Cadastrando nova categoria

        if ($request->category_id == 0) {

            $category = CategoryController::storeInTransaction($request->category_description, $request->type_id);

        } else {

            $category = Category::find($request->category_id);
        }

        // Cadastrando transação

        if (is_null($request->installments)) { // Sem parcelas

            $transaction = Transaction::create([
                'user_id' => Auth::user()->id,
                'category_id' => $category->id,
                'type_id' => $request->type_id,
                'payment_method_id' => $request->payment_method_id,
                'transaction_description' => $request->transaction_description,
                'date' => $request->date,
                'transaction_value' => $request->transaction_value,
            ]);

            $response = [
                'data' => [
                    'trasanction' => $transaction
                ]
            ];

            return response()->json($response, 201);

        } else { // Com parcelas

            $transaction = Transaction::create([
                'user_id' => Auth::user()->id,
                'category_id' => $category->id,
                'type_id' => $request->type_id,
                'payment_method_id' => $request->payment_method_id,
                'card_id' => $request->card_id,
                'transaction_description' => $request->transaction_description,
                'date' => $request->date,
                'transaction_value' => $request->transaction_value,
                'installments' => $request->installments,
            ]);

            $response = [];
            $pay_day = date('Y-m-d');
            $new_pay_day_formated = $pay_day;
            $i = $request->installments;
            $value = (float)$request->transaction_value / (float)$request->installments;
            $value = (float) number_format($value, 2, '.', '');
            $firstInstallment = $request->transaction_value - ($value * ($i - 1));
            $firstInstallment =  (float) number_format($firstInstallment, 2, '.', '');


            for ($i = 1; $i <= $request->installments; $i++) {

                if ($i == 1) {
                    $installment = Installment::create([
                        'transaction_id' => $transaction->id,
                        'installment_description' => $request->transaction_description . ' ' . $i . '/' . $request->installments,
                        'installment_value' => $firstInstallment,
                        'card_id' => $request->card_id,
                        'pay_day' => $pay_day
                    ]);

                    array_push($response, $installment);
                } else {
                    $new_pay_day = strtotime('+1 months', strtotime($pay_day));
                    $new_pay_day_formated = date('Y-m-d', $new_pay_day);
                    $installment = Installment::create([
                        'transaction_id' => $transaction->id,
                        'installment_description' => $request->transaction_description . ' ' . $i . '/' . $request->installments,
                        'installment_value' => $value,
                        'card_id' => $request->card_id,
                        'pay_day' => $new_pay_day_formated
                    ]);

                    array_push($response, $installment);
                }

                $pay_day = $new_pay_day_formated;
            }

            return response()->json($response, 200);
        }
    }

    public function showTransactions(): JsonResponse
    {
        try {
            $transactions = Transaction::orderBy('date', 'desc')
                ->where('user_id', Auth::user()->id)
                ->get();

            $reponse = [
                'transactions' => $transactions
            ];

            return response()->json($reponse, 200);
        } catch (\Exception $e) {
            $errorMessage = 'Nenhuma transação foi encontrada';
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e->getMessage()
                ]
            ];

            return response()->json($response, 404);
        }
    }

    public function showTransaction($id): JsonResponse
    {
        try {

            $transaction = Transaction::find($id);

            $description = CategoryController::showCategory($transaction->category_id);
            $transaction->category_description = $description;

            $response = [
                "transaction" => $transaction
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            $errorMessage = "Erro: Transação não encontrada.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e->getMessage()
                ]
            ];
            return response()->json($response, 404);
        }
    }

    public function showTransactionsByType($id): JsonResponse
    {
        try {

            $transactions = Transaction::where([
                'user_id' => Auth::user()->id,
                'type_id' => $id
            ])->orderBy('date', 'desc')->get();

            $response = [];
            foreach ($transactions  as $transaction) {
                $description = CategoryController::showCategory($transaction->category_id);
                $transaction->category_description = $description;
                array_push($response, $transaction);
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {

            $errorMessage = "Erro: Nenhuma transação encontrada.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e->getMessage()
                ]
            ];
            return response()->json($response, 404);
        }
    }

    public function showTransactionsByCard($id): JsonResponse
    {
        try {

            $transactions = Transaction::where([
                'card_id' => $id
            ])->get();

            $response = [];
            foreach ($transactions  as $transaction) {
                $description = CategoryController::showCategory($transaction->category_id);
                $transaction->category_description = $description;
                array_push($response, $transaction);
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            $errorMessage = "Erro: Este cartão não possui transações.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e->getMessage()
                ]
            ];
            return response()->json($response, 404);
        }
    }

    public function update(Request $request): JsonResponse
    {

        try {

            Transaction::findOrFail($request->id);
        } catch (\Exception $e) {

            $errorMessage = "Erro: Esta transação não existe.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e->getMessage()
                ]
            ];
            return response()->json($response, 404);
        }

        try {

            Transaction::find($request->id)->update($request->all());

            $transaction = Transaction::find($request->id);

            $response = [
                "transaction" => $transaction
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {

            $errorMessage = "Erro: Teste.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e->getMessage()
                ]
            ];
            return response()->json($response, 404);
        }
    }

    public function destroy($id): JsonResponse
    {

        try {

            Transaction::findOrFail($id)->delete();

            $message = 'Transação excluída com sucesso.';

            $response = [
                'data' => [
                    'message' => $message
                ]
            ];
            return response()->json($response, 200);
        } catch (\Exception $e) {

            $errorMessage = "Erro: Esta transação não existe.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e->getMessage()
                ]
            ];
            return response()->json($response, 404);
        }
    }

    public function incomes(Request $request): JsonResponse
    {
        try {
            if ($request->query('sort') == 'day') {

                $incomeByDay = Transaction::whereMonth('date', now()->month)
                    ->whereYear('date', now()->year)
                    ->whereDay('date', '<=', now()->day)
                    ->where('user_id', Auth::user()->id)
                    ->where('type_id', 1)
                    ->get();

                $response = [];

                foreach ($incomeByDay as $income) {
                    $day = date('d', strtotime($income->date));
                    $month = date('m', strtotime($income->date));

                    $income->day = $day;
                    $income->month = $month;
                    $incomeFormatted = [
                        'data' => [
                            'day' => $day,
                            'month' => $month,
                            'ammount' => $income->transaction_value
                        ]
                    ];
                    array_push($response, $incomeFormatted);
                }
            } elseif ($request->query('sort') == 'month') {
                $incomeByMonth = Transaction::where('user_id', Auth::user()->id)
                    ->where('type_id', 1)
                    ->selectRaw('MONTH(date) as month, SUM(transaction_value) as total')
                    ->groupBy('month')
                    ->get();

                $response = [
                    'data' => $incomeByMonth
                ];
            } else {

                $incomeByYear = Transaction::where('user_id', Auth::user()->id)
                    ->where('type_id', 1)
                    ->selectRaw('YEAR(date) as year, SUM(transaction_value) as total')
                    ->groupBy('year')
                    ->get();

                $response = [
                    'data' => $incomeByYear
                ];
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            $errorMessage = "Erro: Nenhuma entrada foi encontrada.";
            $response = [
                "data" => [
                    "message" => $errorMessage,
                    "error" => $e->getMessage()
                ]
            ];
            return response()->json($response, 500);
        }
    }
}
